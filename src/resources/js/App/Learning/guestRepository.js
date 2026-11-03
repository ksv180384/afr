import { migrateGuestState, newGuestState, guestError } from './guestProgress.js';
import { browserTimezone } from './practiceTime.js';

const copy = (value) => structuredClone(value);
export const GUEST_DATABASE = 'afr-guest-practice';
export const GUEST_DATABASE_VERSION = 4;
const migrations = {
  1(db) { db.createObjectStore('progress'); },
  2(db) { db.createObjectStore('sync_receipts', {keyPath:'episode_id'}); },
  3() { /* Vocabulary ledgers migrate atomically with progress/current on initialize. */ },
  4() { /* Sync episodes and sealed batches are added to the progress document. */ },
};

export class GuestProgressRepository {
  constructor({getIndexedDB = () => globalThis.indexedDB, idFactory = () => crypto.randomUUID(), now = () => new Date().toISOString(),
    key = 'current', timezone = browserTimezone(), channelFactory = () => typeof BroadcastChannel === 'undefined' ? null : new BroadcastChannel('afr-guest-practice')} = {}) {
    this.options = {getIndexedDB,idFactory,now,timezone,channelFactory,key};
    this.temporary = false;
    this.cached = null;
    this.database = null;
    this.channel = null;
    this.listeners = new Set();
    this.sender = null;
  }
  async open() {
    if (!this.database) {
      this.database = new Promise((resolve,reject) => {
        const factory = this.options.getIndexedDB();
        if (!factory) { reject(guestError('Хранилище браузера недоступно.', 'storage')); return; }
        const request = factory.open(GUEST_DATABASE,GUEST_DATABASE_VERSION);
        let blocked = false;
        request.onupgradeneeded = (event) => {
          for (let version = event.oldVersion+1; version <= GUEST_DATABASE_VERSION; version++) migrations[version](request.result);
        };
        request.onblocked = () => { blocked = true; reject(guestError('Закройте старые вкладки практики для обновления хранилища.', 'storage')); };
        request.onerror = () => reject(request.error);
        request.onsuccess = () => {
          const db = request.result;
          if (blocked) { db.close(); return; }
          db.onversionchange = () => { db.close(); this.database = null; };
          resolve(db);
        };
      }).catch((error) => { this.database = null; throw error; });
    }
    return this.database;
  }
  async read() {
    if (this.temporary) return copy(this.cached);
    const db = await this.open();
    return new Promise((resolve,reject) => {
      const transaction = db.transaction('progress','readonly');
      let state;
      let failure;
      const request = transaction.objectStore('progress').get(this.options.key);
      request.onsuccess = () => { try { state = request.result ? migrateGuestState(request.result) : null; } catch (error) { failure = error; transaction.abort(); } };
      transaction.oncomplete = () => { this.cached = copy(state); resolve(copy(state)); };
      transaction.onabort = () => reject(failure ?? transaction.error ?? guestError('Не удалось прочитать гостевой прогресс.', 'storage'));
      transaction.onerror = () => {};
    });
  }
  async mutate(apply, notice = null) {
    if (this.temporary) {
      const state = copy(this.cached);
      const result = apply(state);
      this.cached = state;
      return copy(result);
    }
    const db = await this.open();
    return new Promise((resolve,reject) => {
      const transaction = db.transaction('progress','readwrite');
      const store = transaction.objectStore('progress');
      let next;
      let result;
      let failure;
      const request = store.get(this.options.key);
      request.onsuccess = () => {
        try {
          next = request.result ? migrateGuestState(request.result) : null;
          result = apply(next);
          // Initialization returns the initial state; every other action mutates the document.
          if (!next) next = result;
          store.put(next,this.options.key);
        } catch (error) { failure = error; transaction.abort(); }
      };
      transaction.oncomplete = () => {
        this.cached = copy(next);
        try { this.channel ??= this.options.channelFactory(); this.channel?.postMessage({sender:this.sender,...notice}); } catch {}
        resolve(copy(result));
      };
      transaction.onabort = () => reject(failure ?? transaction.error ?? guestError('Не удалось сохранить гостевой прогресс.', 'storage'));
      transaction.onerror = () => {};
    });
  }
  async initialize() {
    const state = await this.read();
    const seed = state ?? newGuestState({idFactory:this.options.idFactory,now:this.options.now(),timezone:this.options.timezone});
    // A real readwrite transaction probes storage before any run starts, and persists upgrades.
    return this.mutate((current) => current ?? seed);
  }
  useTemporary() {
    this.temporary = true;
    this.cached ??= newGuestState({idFactory:this.options.idFactory,now:this.options.now(),timezone:this.options.timezone});
    return copy(this.cached);
  }
  subscribe(sender, listener) {
    this.sender = sender;
    this.listeners.add(listener);
    if (!this.channel) {
      try { this.channel = this.options.channelFactory(); } catch { this.channel = null; }
      if (this.channel) this.channel.onmessage = ({data}) => {
        if (data.sender !== this.sender) for (const callback of this.listeners) callback(data);
      };
    }
    return () => this.listeners.delete(listener);
  }
  async archiveBound(ownerToKeep = null) {
    if (this.options.key !== 'current' || this.temporary) return;
    const db = await this.open();
    return new Promise((resolve,reject) => {
      const tx=db.transaction('progress','readwrite'); const store=tx.objectStore('progress');
      let next; let changed=false;
      const request=store.get('current');
      request.onsuccess=()=>{
        const state=request.result;
        const owner=state?.bound_user_id ?? state?.sync?.owner_user_id;
        if(!state || owner==null || owner===ownerToKeep) return;
        store.put(state,'account:'+owner+':'+state.guest_id);
        next=newGuestState({idFactory:this.options.idFactory,now:this.options.now(),timezone:this.options.timezone});
        store.put(next,'current'); changed=true;
      };
      tx.oncomplete=()=>{ if(changed) {this.cached=copy(next); try {this.channel?.postMessage({sender:this.sender,rotated:true});}catch{}} resolve(changed); };
      tx.onabort=()=>reject(tx.error ?? new Error('Не удалось разделить гостевые данные и кеш аккаунта.'));
      tx.onerror=()=>{};
    });
  }
  async ownerKeys(owner) {
    const db=await this.open();
    return new Promise((resolve,reject)=>{
      const tx=db.transaction('progress','readonly'); const store=tx.objectStore('progress');
      let keys=[]; let states=[];
      const keyRequest=store.getAllKeys(); keyRequest.onsuccess=()=>{keys=keyRequest.result;};
      const dataRequest=store.getAll(); dataRequest.onsuccess=()=>{states=dataRequest.result;};
      tx.oncomplete=()=>resolve(keys.filter((key,index)=>key!=='current' && (states[index]?.bound_user_id ?? states[index]?.sync?.owner_user_id)===owner));
      tx.onabort=()=>reject(tx.error); tx.onerror=()=>{};
    });
  }

  close() {
    this.channel?.close();
    this.channel = null;
    this.database?.then((db) => db.close()).catch(() => {});
    this.database = null;
  }
}
