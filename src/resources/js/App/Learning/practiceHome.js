import { watch } from 'vue';
import { createPracticeHomeStore } from './practiceHomeStore.js';
import { createAccountRepository } from './accountRepository.js';
import { GuestProgressRepository } from './guestRepository.js';
import { guestSyncRevision } from './guestSyncState.js';
import { accountTransport, guestContentTransport } from '../Services/Api/daily_practice.js';
import { browserTimezone } from './practiceTime.js';

let clientStore;
const create = () => {
  const timezone=browserTimezone();
  return createPracticeHomeStore({transport:accountTransport,guestTransport:guestContentTransport,
    guestRepository:new GuestProgressRepository({timezone}),accountRepository:createAccountRepository()});
};
export const usePracticeHome = () => {
  if(typeof window==='undefined') return create();
  if(!clientStore) {
    clientStore=create();
    watch(guestSyncRevision,()=>{if(clientStore.state.owner!=null) clientStore.refresh(clientStore.state.owner).catch(()=>{});});
    clientStore.guestRepository.subscribe('home-'+crypto.randomUUID(),(notice)=>{
      if(clientStore.state.owner===null && (notice.claimed || notice.profile || notice.bound || notice.rotated)) clientStore.refresh(null).catch(()=>{});
    });
  }
  return clientStore;
};
