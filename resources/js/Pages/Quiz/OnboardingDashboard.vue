<script setup>
import { ref, onMounted } from 'vue';
import OnboardingSlides from './Components/OnboardingSlides.vue';
import AuthLogin from './Components/AuthLogin.vue';
import GameDashboard from './Components/GameDashboard.vue';

// State: 'ONBOARDING' | 'LOGIN' | 'DASHBOARD'
const viewState = ref('ONBOARDING');
const currentUser = ref(null);

onMounted(() => {
    const token = localStorage.getItem('auth_token');
    const storedUser = localStorage.getItem('user_data');

    if (token && storedUser) {
        currentUser.value = JSON.parse(storedUser);
        viewState.value = 'DASHBOARD';
    } else {
        viewState.value = 'ONBOARDING';
    }
});

const onLoginSuccess = (userData) => {
    currentUser.value = userData;
    viewState.value = 'DASHBOARD';
};

const onLogout = () => {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user_data');
    currentUser.value = null;
    viewState.value = 'LOGIN';
};
</script>

<template>
    <div class="min-h-screen bg-slate-950 flex items-center justify-center p-0 sm:p-6 font-sans">
        <!-- Mobile Frame Wrapper -->
        <div class="w-full sm:max-w-md h-screen sm:h-[844px] bg-slate-50 sm:rounded-[40px] shadow-2xl overflow-hidden relative border-0 sm:border-[8px] sm:border-slate-800">
            
            <!-- 1. Onboarding Component -->
            <OnboardingSlides 
                v-if="viewState === 'ONBOARDING'" 
                @complete="viewState = 'LOGIN'" 
                @skip="viewState = 'LOGIN'" 
            />

            <!-- 2. Auth Login Component -->
            <AuthLogin 
                v-else-if="viewState === 'LOGIN'" 
                @login-success="onLoginSuccess" 
                @back="viewState = 'ONBOARDING'" 
            />

            <!-- 3. Dashboard Component -->
            <GameDashboard 
                v-else-if="viewState === 'DASHBOARD'" 
                :user="currentUser" 
                @logout="onLogout" 
            />

        </div>
    </div>
</template>