import { shallowRef, ref } from 'vue';
export const guestSyncUi = shallowRef({kind:'idle',message:''});
export const guestSyncRevision = ref(0);
let temporaryGuest = null;
export const rememberTemporaryGuest = (state) => { temporaryGuest = structuredClone(state); };
export const temporaryGuestState = () => temporaryGuest;
export const clearTemporaryGuest = () => { temporaryGuest = null; };

let authenticationPreparation = null;
export const registerGuestAuthentication = (prepare) => {
  authenticationPreparation = prepare;
  return () => { if (authenticationPreparation === prepare) authenticationPreparation = null; };
};
export const hasGuestAuthentication = () => authenticationPreparation !== null;
export const prepareGuestAuthentication = async () => {
  if (!authenticationPreparation) return false;
  await authenticationPreparation();
  return true;
};
