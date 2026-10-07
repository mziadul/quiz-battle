<script setup>
import { ref } from 'vue';

const emit = defineEmits(['complete', 'skip']);
const currentStep = ref(0);

const onboardingSlides = [
    { type: 'splash', image: '/images/splash.png' },
    { 
        type: 'slide', 
        tag: 'TRAIN YOUR BRAIN', 
        title: 'Turn knowledge into a superpower.', 
        subtitle: 'Fast, fun quiz battles across the topics you love.', 
        image: '/images/slide1.png', 
        badgeColor: 'bg-purple-100 text-purple-600' 
    },
    { 
        type: 'slide', 
        tag: 'REAL-TIME BATTLES', 
        title: 'Challenge players. Climb the ranks.', 
        subtitle: 'Match instantly or invite a friend to your private room.', 
        image: '/images/slide2.png', 
        badgeColor: 'bg-blue-100 text-blue-600' 
    },
    { 
        type: 'slide', 
        tag: 'WIN & GROW', 
        title: 'Every answer moves you forward.', 
        subtitle: 'Earn XP, unlock badges, and rise through weekly leagues.', 
        image: '/images/slide3.png', 
        badgeColor: 'bg-amber-100 text-amber-600' 
    }
];

const nextStep = () => {
    if (currentStep.value < onboardingSlides.length - 1) {
        currentStep.value++;
    } else {
        emit('complete');
    }
};
</script>

<template>
    <div class="h-full flex flex-col justify-between transition-all duration-300">
        
        <!-- ============================================== -->
        <!-- STEP 0: FULL IMAGE SPLASH SCREEN               -->
        <!-- ============================================== -->
        <div 
            v-if="currentStep === 0" 
            @click="nextStep"
            class="h-full w-full relative cursor-pointer select-none bg-indigo-600"
        >
            <!-- Provided Splash Image -->
            <img 
                src="/images/splash.png" 
                alt="Quiz Battle Splash" 
                class="w-full h-full object-cover"
            />

            <!-- Tap Prompt Overlay -->
            <p class="absolute bottom-8 left-0 right-0 text-center text-xs font-bold text-white/80 uppercase tracking-widest animate-pulse">
                Tap anywhere to continue &rsaquo;
            </p>
        </div>

        <!-- ============================================== -->
        <!-- STEPS 1, 2, 3: ONBOARDING SLIDES WITH IMAGES   -->
        <!-- ============================================== -->
        <div v-else class="h-full flex flex-col justify-between p-6 bg-white">
            <!-- Top Bar -->
            <div class="flex justify-between items-center pt-2">
                <span class="text-xs font-bold tracking-widest text-indigo-600 uppercase">
                    Step {{ currentStep }} of 3
                </span>
                <button @click="emit('skip')" class="text-xs font-bold text-slate-400 hover:text-slate-600">
                    Skip
                </button>
            </div>

            <!-- Slide Content with Cropped 3D Asset Image -->
            <div class="flex flex-col items-center text-center my-auto space-y-6">
                <div class="w-48 h-48 flex items-center justify-center">
                    <img 
                        :src="onboardingSlides[currentStep].image" 
                        :alt="onboardingSlides[currentStep].title" 
                        class="max-w-full max-h-full object-contain"
                    />
                </div>

                <div class="space-y-2">
                    <span :class="onboardingSlides[currentStep].badgeColor" class="px-3 py-1 rounded-full text-[10px] font-black tracking-wider">
                        {{ onboardingSlides[currentStep].tag }}
                    </span>
                    <h2 class="text-2xl font-black text-slate-800 leading-tight">
                        {{ onboardingSlides[currentStep].title }}
                    </h2>
                    <p class="text-xs text-slate-500 max-w-xs mx-auto leading-relaxed">
                        {{ onboardingSlides[currentStep].subtitle }}
                    </p>
                </div>
            </div>

            <!-- Bottom Controls -->
            <div class="space-y-6 pb-4">
                <div class="flex justify-center space-x-2">
                    <span 
                        v-for="i in 3" 
                        :key="i" 
                        :class="currentStep === i ? 'w-6 bg-indigo-600' : 'w-2 bg-slate-200'" 
                        class="h-2 rounded-full transition-all duration-300"
                    ></span>
                </div>
                <button 
                    @click="nextStep" 
                    class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl font-bold text-sm shadow-xl shadow-indigo-200 transition-all flex items-center justify-center gap-2"
                >
                    <span>{{ currentStep === 3 ? 'Continue to Login' : 'Next' }}</span>
                    <span>&rsaquo;</span>
                </button>
            </div>
        </div>

    </div>
</template>