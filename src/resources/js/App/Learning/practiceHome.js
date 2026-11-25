import { randomUuid } from './randomUuid.js';
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
    watch(guestSyncRevision, () => {
      if (clientStore.state.owner != null) clientStore.invalidate(clientStore.state.owner);
    });
    clientStore.guestRepository.subscribe('home-'+randomUuid(),(notice)=>{
      if (clientStore.state.owner === null && (notice.run_id || notice.profile || notice.bound || notice.rotated)) clientStore.invalidate(null);
    });
  }
  return clientStore;
};
