const copy = (value) => structuredClone(value);
const logicalKey = (value) => JSON.stringify(value, (_, item) => item && typeof item === 'object' && !Array.isArray(item)
  ? Object.fromEntries(Object.keys(item).sort().map((key) => [key, item[key]])) : item);
export { logicalKey };

export const createAccountRepository = ({ indexedDB = () => globalThis.indexedDB, name = 'afr-account-practice-v1' } = {}) => {
  let connection;
  const open = () => {
    if (connection) return connection;
    connection = new Promise((resolve, reject) => {
      const api = indexedDB();
      if (!api) { reject(new Error('IndexedDB недоступна. Черновик пока только в открытой вкладке.')); return; }
      const request = api.open(name, 1);
      request.onupgradeneeded = () => {
        const db = request.result;
        const drafts = db.createObjectStore('drafts', { keyPath: 'key' });
        drafts.createIndex('owner', 'owner_user_id');
        const events = db.createObjectStore('events', { keyPath: 'local_id', autoIncrement: true });
        events.createIndex('event', 'client_event_id', { unique: true });
        events.createIndex('owner', 'owner_user_id');
      };
      request.onerror = () => reject(request.error);
      request.onblocked = () => reject(new Error('Закройте другую вкладку, блокирующую хранилище, и повторите сохранение.'));
      request.onsuccess = () => {
        const db = request.result;
        db.onversionchange = () => { db.close(); connection = null; };
        resolve(db);
      };
    }).catch((error) => { connection = null; throw error; });
    return connection;
  };
  const transact = async (table, mode, operation) => {
    const db = await open();
    return new Promise((resolve, reject) => {
      const transaction = db.transaction(table, mode);
      let result;
      transaction.oncomplete = () => resolve(copy(result));
      transaction.onerror = transaction.onabort = () => reject(transaction.error ?? new Error('Не удалось сохранить данные на устройстве.'));
      try { operation(transaction.objectStore(table), (value) => { result = value; }); }
      catch (error) { transaction.abort(); reject(error); }
    });
  };
  const draftKey = (owner, run, presentation) => owner + ':' + run + ':' + presentation;
  return {
    async draft(owner, run, presentation) {
      return transact('drafts', 'readonly', (store, done) => { const request = store.get(draftKey(owner, run, presentation)); request.onsuccess = () => done(request.result?.answer ?? null); });
    },
    async saveDraft(owner, run, presentation, answer) {
      return transact('drafts', 'readwrite', (store, done) => { store.put({ schema_version:1, key:draftKey(owner,run,presentation), owner_user_id:owner, run_id:run, presentation_id:presentation, answer:copy(answer), updated_at:Date.now() }); done(true); });
    },
    async enqueue(owner, run, action, logical, requestBody) {
      return transact('events', 'readwrite', (store, done) => {
        const get = store.index('event').get(logical.client_event_id);
        get.onsuccess = () => {
          const previous = get.result;
          if (previous) {
            if (previous.owner_user_id !== owner || previous.run_id !== run || previous.action !== action || previous.logical_hash !== logicalKey(logical)) {
              store.transaction.abort(); return;
            }
            done(previous); return;
          }
          const row = { schema_version:1, owner_user_id:owner, run_id:run, action, client_event_id:logical.client_event_id,
            logical_hash:logicalKey(logical), request:copy(requestBody), status:'queued', queued_at:Date.now() };
          const add = store.add(row);
          add.onsuccess = () => done({...row,local_id:add.result});
        };
      });
    },
    async pending(owner, run) {
      return transact('events', 'readonly', (store, done) => {
        const request = store.index('owner').getAll(owner);
        request.onsuccess = () => done(request.result.filter((row) => (run === undefined || row.run_id === run) && !['acked','dismissed'].includes(row.status)).sort((a,b) => (a.queue_order ?? a.local_id)-(b.queue_order ?? b.local_id)));
      });
    },
    async seal(owner, localId, revision) {
      return transact('events', 'readwrite', (store, done) => {
        const get = store.get(localId);
        get.onsuccess = () => {
          const row = get.result;
          if (!row || row.owner_user_id !== owner) { store.transaction.abort(); return; }
          if (row.status !== 'queued') { done(row); return; }
          const next = {...row,status:'sent',request:{...row.request}};
          if (revision !== undefined) next.request.expected_revision = revision;
          store.put(next); done(next);
        };
      });
    },
    async mark(owner, localId, status, receipt) {
      return transact('events', 'readwrite', (store, done) => {
        const get = store.get(localId);
        get.onsuccess = () => {
          const row = get.result;
          if (!row || row.owner_user_id !== owner) { store.transaction.abort(); return; }
          const next = {...row,status};
          if (receipt) next.acknowledgement = { accepted_event_id:receipt.accepted_event_id, run_id:receipt.state?.run_id, revision:receipt.state?.revision };
          store.put(next); done(next);
        };
      });
    },
    async replaceRejected(owner, localId, requestBody) {
      return transact('events', 'readwrite', (store, done) => {
        const get = store.get(localId);
        get.onsuccess = () => {
          const previous = get.result;
          if (!previous || previous.owner_user_id !== owner || ['acked','dismissed'].includes(previous.status)) { store.transaction.abort(); return; }
          store.put({...previous, status:'dismissed'});
          const next = {schema_version:1, owner_user_id:owner, run_id:previous.run_id, action:previous.action,
            client_event_id:requestBody.client_event_id, logical_hash:logicalKey(requestBody), request:copy(requestBody), status:'queued', queued_at:Date.now(), queue_order:previous.queue_order ?? previous.local_id};
          const add = store.add(next);
          add.onsuccess = () => done({...next,local_id:add.result});
        };
      });
    },
  };
};
