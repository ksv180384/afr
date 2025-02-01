<script setup>
import { ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Icon } from '@iconify/vue';
import api from '@/App/Services/Api';

import UpdatePasswordForm from '@/App/Pages/Profile/Components/UpdatePasswordForm.vue';
import DefaultLayout from '@/App/Layouts/DefaultLayout.vue';
import AfrMixedInput from '@/App/Components/Form/AfrMixedInput.vue';
import AfrSelect from '@/App/Components/Form/Select/AfrSelect.vue';
import AfrOption from '@/App/Components/Form/Select/AfrOption.vue';
import AfrTabs from '@/App/Components/Tabs/AfrTabs.vue';
import AfrTabPlane from '@/App/Components/Tabs/AfrTabPlane.vue';
import AfrUploadImage from '@/App/Components/Form/AfrUploadImage.vue';
import AfrButton from '@/App/Components/Form/AfrButton.vue';
import AfrTextarea from '@/App/Components/Form/AfrTextarea.vue';
import AfrCheckbox from '@/App/Components/Form/AfrCheckbox.vue';
import InputError from "@/App/Components/InputError.vue";

const props = defineProps({
  words: { type: Array, default: [] },
  proverb: { type: Object, default: {} },
  mustVerifyEmail: { type: Boolean, },
  user: { type: Object, },
  genders: { type: Array, },
  userConfigsView: { type: Array, },
  status: { type: String, },
});

const page = usePage();

const form = useForm({
  name: props.user.name,
  gender_id: props.user.gender_id || '',
  residence: props.user.residence,
  birthday: props.user.birthday,
  info: props.user.info,
  day_birthday: props.user.config.day_birthday,
  yar_birthday: props.user.config.yar_birthday,
  view_email: props.user.config.email.id,
  view_info: props.user.config.info.id,
  view_residence: props.user.config.residence.id,
  view_sex: props.user.config.sex.id,
  odnoklassniki: props.user.infos.odnoklassniki,
  view_odnoklassniki: props.user.config.odnoklassniki.id,
  vk: props.user.infos.vk,
  view_vk: props.user.config.vk.id,
  youtube: props.user.infos.youtube,
  view_youtube: props.user.config.youtube.id,
  skype: props.user.infos.skype,
  view_skype: props.user.config.skype.id,
  telegram: props.user.infos.telegram,
  view_telegram: props.user.config.telegram.id,
  whatsapp: props.user.infos.whatsapp,
  view_whatsapp: props.user.config.whatsapp.id,
});
const avatarLink = ref(props.user.avatar ? props.user.avatar_link : '');
const tabActive = ref('profile');
const isUploadingAvatar = ref(false);

const submitForm = () => {
  form.patch(route('profile.update'));
}

const uploadAvatar = async (image) => {

  isUploadingAvatar.value = true;
  try {
    const formData = new FormData();
    formData.append('avatar', image);
    const res = await api.user.uploadAvatar(formData);

    page.props.user.avatar = res.data.avatar;
    page.props.user.avatar_link = res.data.avatar_link;
    page.props.errors.avatar = null;
  } catch (e) {
    console.error(e);
    page.props.errors.avatar = e.response.data.errors.avatar;
  } finally {
    isUploadingAvatar.value = false;
  }
}

const removeAvatar = async () => {
  isUploadingAvatar.value = true;
  try {

    const res = await api.user.removeAvatar();

    page.props.user.avatar = res.data.avatar;
    page.props.user.avatar_link = res.data.avatar_link;
    page.props.errors.avatar = null;
  } catch (e) {
    console.error(e);
    page.props.errors.avatar = e.response.data.errors.avatar;
  } finally {
    isUploadingAvatar.value = false;
  }
}
</script>

<template>
  <Head>
    <title>Ваш профиль</title>
    <meta name="description" content="Ваш профиль" />
    <meta property="og:title" content="Ваш профиль" />
    <meta property="og:description" content="Ваш профиль" />
  </Head>

  <default-layout
    :auth-user="user"
    :words="words"
    :proverb="proverb"
  >

    <div class="bg-blue-50 p-4 rounded min-h-full">
      <afr-tabs v-model="tabActive">
        <afr-tab-plane label="Профиль" name="profile" class="py-4">

          <form @submit.prevent="submitForm">
            <div class="form-user-params-container">
              <div class="form-user-params-item">
                <div class="flex flex-col lg:flex-row gap-2">
                  <div class="text-center">
                    <afr-upload-image
                      v-model="avatarLink"
                      class="h-[240px] w-[180px] mx-auto lg:mx-0"
                      :loading="isUploadingAvatar"
                      @change="uploadAvatar"
                      @remove="removeAvatar"
                    />

                    <div v-if="page.props.errors?.avatar?.length" class="text-center">
                      <InputError class="mt-2" :message="page.props.errors.avatar[0]" />
                    </div>
                  </div>
                  <div class="flex flex-col gap-3 w-full overflow-hidden">
                    <div>
                      <afr-mixed-input v-model="form.name">
                        <template #prepend>
                          Имя
                        </template>
                      </afr-mixed-input>
                    </div>

                    <div>
                      <afr-mixed-input :model-value="user.email" readonly>
                        <template #prepend>
                          Email
                        </template>
                      </afr-mixed-input>
                    </div>

                    <div>
                      <afr-select v-model="form.gender_id" class="w-full">
                        <template #prepend>
                          Пол
                        </template>
                        <afr-option label="Нет" value=""/>
                        <afr-option
                          v-for="gender in genders"
                          :key="gender.id"
                          :label="gender.title"
                          :value="gender.id"
                        />
                      </afr-select>
                    </div>

                    <div>
                      <afr-mixed-input v-model="form.residence" placeholder="Место жительства">
                        <template #prepend>
                          Город
                        </template>
                      </afr-mixed-input>
                    </div>

                    <div class="flex-1">
                      <afr-mixed-input
                        v-model="form.birthday"
                        placeholder="Дата рождения"
                        :native-type="`date`"
                      >
                        <template #prepend>
                          Дата рождения
                        </template>
                      </afr-mixed-input>
                    </div>

                    <div>
                      <afr-checkbox
                        v-model="form.day_birthday"
                        id="day_birthday"
                        class="flex-grow"
                        label="Показывать дату рождения (день, месяц)"
                      />
                    </div>

                    <div>
                      <afr-checkbox
                        v-model="form.yar_birthday"
                        id="yar_birthday"
                        class="flex-grow"
                        label="Показывать возраст (год рождения)"
                      />
                    </div>
                  </div>
                </div>

                <div class="mt-4">
                  <afr-textarea v-model="form.info" label="О себе" placeholder="О себе" rows="4"></afr-textarea>
                </div>
              </div>

              <div class="form-user-params-item">

                <div class="text-center pt-3 lg:pt-0 pb-[1.4rem]">
                  Настройка отображения информации
                </div>

                <div class="flex flex-1 flex-col">
                  <div class="flex flex-col">
                    <div class="flex flex-col lg:flex-row gap-2">
                      <div class="flex flex-col gap-3 flex-1 overflow-hidden">
                        <div>
                          <afr-select
                            v-model="form.view_email"
                            placeholder="Email"
                          >
                            <template #prepend>
                              Email
                            </template>
                            <afr-option
                              v-for="viewConf in userConfigsView"
                              :key="viewConf.id"
                              :label="viewConf.title"
                              :value="viewConf.id"
                            />
                          </afr-select>
                        </div>

                        <div>
                          <afr-select
                            v-model="form.view_info"
                            placeholder="О себе"
                          >
                            <template #prepend>
                              О себе
                            </template>
                            <afr-option
                              v-for="viewConf in userConfigsView"
                              :key="viewConf.id"
                              :label="viewConf.title"
                              :value="viewConf.id"
                            />
                          </afr-select>
                        </div>
                      </div>
                      <div class="flex flex-col gap-3 flex-1 overflow-hidden">

                        <div>
                          <afr-select
                            v-model="form.view_residence"
                            placeholder="Место жительства"
                          >
                            <template #prepend>
                              Место жительства
                            </template>
                            <afr-option
                              v-for="viewConf in userConfigsView"
                              :key="viewConf.id"
                              :label="viewConf.title"
                              :value="viewConf.id"
                            />
                          </afr-select>
                        </div>

                        <div>
                          <afr-select
                            v-model="form.view_sex"
                            placeholder="Пол"
                          >
                            <template #prepend>
                              Пол
                            </template>
                            <afr-option
                              v-for="viewConf in userConfigsView"
                              :key="viewConf.id"
                              :label="viewConf.title"
                              :value="viewConf.id"
                            />
                          </afr-select>
                        </div>
                      </div>
                    </div>

                    <div class="contacts-container">

                      <div class="contacts-container-header">
                        Контакты
                      </div>

                      <div class="contacts-item">
                        <afr-mixed-input
                          v-model="form.odnoklassniki"
                          placeholder="profile/xxxxxxxxxxxx"
                        >
                          <template #prepend>
                            <Icon icon="fa-brands:odnoklassniki-square" width="20px" height="20px" /> ok.ru/
                          </template>
                          <template #append>
                            <afr-select
                              v-model="form.view_odnoklassniki"
                            >
                              <afr-option
                                v-for="viewConf in userConfigsView"
                                :key="viewConf.id"
                                :label="viewConf.title"
                                :value="viewConf.id"
                              />
                            </afr-select>
                          </template>
                        </afr-mixed-input>
                      </div>

                      <div class="contacts-item">
                        <afr-mixed-input
                          v-model="form.vk"
                          placeholder="xxxxxxxxxxxx"
                        >
                          <template #prepend>
                            <Icon icon="ri:vk-fill" width="20px" height="20px" /> vk.com/
                          </template>
                          <template #append>
                            <afr-select
                              v-model="form.view_vk"
                            >
                              <afr-option
                                v-for="viewConf in userConfigsView"
                                :key="viewConf.id"
                                :label="viewConf.title"
                                :value="viewConf.id"
                              />
                            </afr-select>
                          </template>
                        </afr-mixed-input>
                      </div>

                      <div class="contacts-item">
                        <afr-mixed-input
                          v-model="form.youtube"
                          placeholder="xxxxxxxxxxxx"
                        >
                          <template #prepend>
                            <Icon icon="mdi:youtube" width="20px" height="20px" /> youtube.com/
                          </template>
                          <template #append>
                            <afr-select
                              v-model="form.view_youtube"
                            >
                              <afr-option
                                v-for="viewConf in userConfigsView"
                                :key="viewConf.id"
                                :label="viewConf.title"
                                :value="viewConf.id"
                              />
                            </afr-select>
                          </template>
                        </afr-mixed-input>
                      </div>

                      <div class="contacts-item">
                        <afr-mixed-input
                          v-model="form.skype"
                          placeholder="Skype"
                        >
                          <template #prepend>
                            <Icon icon="mdi:skype" width="20px" height="20px" />
                          </template>
                          <template #append>
                            <afr-select
                              v-model="form.view_skype"
                            >
                              <afr-option
                                v-for="viewConf in userConfigsView"
                                :key="viewConf.id"
                                :label="viewConf.title"
                                :value="viewConf.id"
                              />
                            </afr-select>
                          </template>
                        </afr-mixed-input>
                      </div>

                      <div class="contacts-item">
                        <afr-mixed-input
                          v-model="form.telegram"
                          placeholder="Telegram"
                        >
                          <template #prepend>
                            <Icon icon="ic:baseline-telegram" width="20px" height="20px" />
                          </template>
                          <template #append>
                            <afr-select
                              v-model="form.view_telegram"
                            >
                              <afr-option
                                v-for="viewConf in userConfigsView"
                                :key="viewConf.id"
                                :label="viewConf.title"
                                :value="viewConf.id"
                              />
                            </afr-select>
                          </template>
                        </afr-mixed-input>
                      </div>

                      <div class="contacts-item">
                        <afr-mixed-input
                          v-model="form.whatsapp"
                          placeholder="Whatsapp"
                        >
                          <template #prepend>
                            <Icon icon="ic:baseline-whatsapp" width="20px" height="20px" />
                          </template>
                          <template #append>
                            <afr-select
                              v-model="form.view_whatsapp"
                            >
                              <afr-option
                                v-for="viewConf in userConfigsView"
                                :key="viewConf.id"
                                :label="viewConf.title"
                                :value="viewConf.id"
                              />
                            </afr-select>
                          </template>
                        </afr-mixed-input>
                      </div>

                    </div>
                  </div>
                </div>

              </div>
            </div>

            <div class="flex flex-col justify-center py-5 mt-8">
              <div class="text-center mb-6">
                <template v-for="error in form.errors">
                  <InputError class="mt-2" :message="error" />
                </template>
              </div>
              <afr-button
                size="large"
                type="primary"
                native-type="submit"
                :loading="form.processing"
              >
                Сохранить
              </afr-button>
            </div>
          </form>

        </afr-tab-plane>
        <afr-tab-plane label="Сменить пароль" name="change_password" class="py-4">
          <UpdatePasswordForm class="max-w-xl" />
        </afr-tab-plane>
      </afr-tabs>


<!--      <UpdateProfileInformationForm-->
<!--        :must-verify-email="mustVerifyEmail"-->
<!--        :status="status"-->
<!--        class="max-w-xl"-->
<!--      />-->
    </div>

<!--    <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">-->
<!--      <UpdatePasswordForm class="max-w-xl" />-->
<!--    </div>-->

<!--    <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">-->
<!--      <DeleteUserForm class="max-w-xl" />-->
<!--    </div>-->


  </default-layout>
</template>

<style scoped>
.form-user-params-container{
  @apply flex flex-wrap gap-2;
}

.form-user-params-item{
  @apply flex flex-1 flex-col overflow-hidden;
  flex-basis: 300px;
}

.contacts-container{
  /*@apply mt-16;*/
  /*margin-top: 3.7rem;*/
}

.contacts-container-header{
  @apply text-center text-sm;
  margin-top: 1.22rem;
  margin-bottom: 1.22rem;
}

.contacts-item:not(:last-child){
  @apply mb-3
}

</style>
