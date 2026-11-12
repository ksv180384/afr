import { changeGuestReviewSettings, reviewSuggestionsView } from './reviewSuggestions.js';

export const createReviewSettingsController = ({transport, guestRepository, getOwner, onMutation = () => {}, state = {},
  now=()=>new Date().toISOString(), idFactory=()=>crypto.randomUUID()}) => {
  Object.assign(state,{ready:false,busy:false,pending:false,profile:null,error:''});
  let owner; let generation=0; let request=null;
  const checkOwner=()=>{const next=getOwner();if(next!==owner){owner=next;generation++;request=null;Object.assign(state,{ready:false,pending:false,profile:null,error:''});}return generation;};
  const guard=token=>{checkOwner();if(token!==generation) throw Object.assign(new Error('Аккаунт изменился. Повторите действие в нужном аккаунте.'),{code:'owner_changed'});};
  const load=async()=>{
    const token=checkOwner();
    const profile=owner===null ? await guestRepository.read().then(local=>({timezone:local.timezone,revision:local.review_suggestions.revision,
      review_suggestions:reviewSuggestionsView(local.review_suggestions,now())})) : await transport.profile();
    guard(token);
    if(owner!==null && profile.owner_user_id!==owner) throw new Error('Войдите в исходный аккаунт.');
    state.profile=profile;state.ready=true;state.error='';return profile;
  };
  const save=async(action,enabled)=>{
    if(state.busy) return;
    const token=checkOwner();state.busy=true;state.error='';
    try {
      if(!state.ready) await load();guard(token);
      request ??= {client_event_id:idFactory(),expected_revision:state.profile.revision,action,...(action==='toggle'?{enabled}:{})};
      state.pending=true;
      let saved;
      if(owner===null) {
        saved = await guestRepository.mutate(local => {
          const instant = now();
          changeGuestReviewSettings(local, request, instant);
          const settings = local.review_suggestions;
          return {timezone:local.timezone, revision:settings.revision, review_suggestions:reviewSuggestionsView(settings, instant)};
        }, {profile:true,review_settings:true});
      } else {
        const profile=await transport.profile();guard(token);
        if(profile.owner_user_id!==owner) throw new Error('Войдите в исходный аккаунт.');
        const receipt=await transport.updateReviewSettings(request);guard(token);
        if(receipt.profile.owner_user_id!==owner) throw new Error('Войдите в исходный аккаунт.');
        saved = receipt.profile.revision >= profile.revision ? receipt.profile : profile;
      }
      guard(token);
      state.profile = saved;
      request=null;state.pending=false;
      onMutation(owner);
    } catch(failure) {
      if(token!==generation) throw failure;
      const status=failure.response?.status;
      if(status===409 || failure.code==='revision_conflict' || (status && status<500 && status!==429) || ['bound_owner','validation_failed','owner_changed','idempotency_conflict'].includes(failure.code)) {
        request=null;state.pending=false;
        if(status===409 || failure.code==='revision_conflict') {await load();failure=new Error('Настройки изменены на другом устройстве или в другой вкладке. Повторите выбор.');}
      }
      state.error=failure.response?.data?.message ?? failure.message;throw failure;
    } finally {state.busy=false;}
  };
  return {state,load,save};
};
