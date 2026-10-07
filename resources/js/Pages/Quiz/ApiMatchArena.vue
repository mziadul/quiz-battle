<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    sessionId: Number
});

const questions = ref([]);
const currentIdx = ref(0);
const selectedOption = ref(null);
const isLocked = ref(false);

const countdown = ref(3);
const isGameStarted = ref(false);
const timer = ref(10);
const isLoading = ref(true);

const userAnswers = ref([]);
const isWaitingResult = ref(false);
const matchResult = ref(null);
const currentUser = ref(null);

let timerInterval = null;
let pollingInterval = null;

onMounted(() => {
    const token = localStorage.getItem('auth_token');
    const storedUser = localStorage.getItem('user_data');
    if (token) axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    if (storedUser) currentUser.value = JSON.parse(storedUser);

    fetchAndSyncMatch();
});

// 1. Fetch Questions & Sync State (With Countdown Trigger)
const fetchAndSyncMatch = async () => {
    isLoading.value = true;
    try {
        const res = await axios.get(`/api/quiz/questions/${props.sessionId}`);
        
        if (res.data.status === 'SUCCESS') {
            // ১. যদি সেশন সম্পূর্ণ শেষ হয়ে থাকে
            if (res.data.session_status === 'completed' && res.data.results) {
                matchResult.value = res.data.results;
                isLoading.value = false;
                return;
            }

            // ২. প্রশ্ন সেভ করা
            questions.value = res.data.questions || [];

            const serverIndex = res.data.current_index ?? 0;
            const remaining = res.data.remaining_seconds ?? 10;

            // ৩. নতুন সেশন (Question Index 0 এবং টাইমার 9s-এর বেশি বাকি) হলে ৩-২-১ কাউন্টডাউন চলবে
            if (serverIndex === 0 && remaining >= 9) {
                isLoading.value = false;
                startCountdown();
            } else {
                // ম্যাচ অলরেডি রানিং থাকলে কাউন্টডাউন স্কিপ করে বর্তমান প্রশ্নে চলে যাবে
                currentIdx.value = serverIndex;
                timer.value = remaining;
                isLoading.value = false;
                isGameStarted.value = true;
                startQuestionTimer(true, remaining);
            }
        }
    } catch (err) {
        console.error('Fetch Questions Error:', err);
        isLoading.value = false;
    }
};

// 2. 3-2-1 Countdown Timer Sequence
const startCountdown = () => {
    countdown.value = 3;
    isGameStarted.value = false;

    const interval = setInterval(() => {
        if (countdown.value <= 1) {
            clearInterval(interval);
            isGameStarted.value = true;
            startQuestionTimer(false, 10);
        } else {
            countdown.value--;
        }
    }, 1000);
};

// 3. Question 10s Timer
const startQuestionTimer = (isResume = false, initialTime = 10) => {
    isLocked.value = false;
    selectedOption.value = null;
    timer.value = initialTime;

    if (timerInterval) clearInterval(timerInterval);

    timerInterval = setInterval(() => {
        if (timer.value <= 1) {
            clearInterval(timerInterval);
            submitOption(null, true);
        } else {
            timer.value--;
        }
    }, 1000);
};

// 4. Submit Single Option
const submitOption = (optKey, isTimeout = false) => {
    if (isLocked.value) return;
    isLocked.value = true;
    selectedOption.value = optKey;

    if (timerInterval) clearInterval(timerInterval);

    const currentQ = questions.value[currentIdx.value];
    if (currentQ) {
        userAnswers.value.push({
            question_id: currentQ.id,
            selected_option: optKey,
            time_taken: 10 - timer.value
        });
    }

    setTimeout(() => {
        nextQuestion();
    }, isTimeout ? 0 : 300);
};

// 5. Next Question Flow
const nextQuestion = () => {
    if (currentIdx.value < questions.value.length - 1) {
        currentIdx.value++;
        startQuestionTimer(false, 10);
    } else {
        submitFinalAnswers();
    }
};

// 6. Submit Final Answers
const submitFinalAnswers = async () => {
    isWaitingResult.value = true;
    try {
        const res = await axios.post(`/api/quiz/submit-answers/${props.sessionId}`, {
            answers: userAnswers.value
        });

        if (res.data.status === 'COMPLETED') {
            matchResult.value = res.data.results;
            isWaitingResult.value = false;
        } else {
            startPollingResult();
        }
    } catch (err) {
        console.error('Final Submit Error:', err);
    }
};

// 7. Polling Backup for Opponent Result
const startPollingResult = () => {
    if (pollingInterval) clearInterval(pollingInterval);
    pollingInterval = setInterval(async () => {
        try {
            const res = await axios.get(`/api/quiz/questions/${props.sessionId}`);
            if (res.data.status === 'SUCCESS' && res.data.session_status === 'completed') {
                clearInterval(pollingInterval);
                matchResult.value = res.data.results;
                isWaitingResult.value = false;
            }
        } catch (err) {
            console.error('Polling Error:', err);
        }
    }, 2500);
};

const goDashboard = () => {
    router.visit('/app/dashboard');
};

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
    if (pollingInterval) clearInterval(pollingInterval);
});
</script>

<template>
    <div class="min-h-screen bg-slate-950 flex items-center justify-center p-0 sm:p-6 font-sans">
        <div class="w-full sm:max-w-md h-screen sm:h-[844px] bg-slate-900 sm:rounded-[40px] shadow-2xl overflow-hidden relative border-0 sm:border-[8px] sm:border-slate-800 flex flex-col justify-between p-6">
            
            <!-- Loading -->
            <div v-if="isLoading" class="m-auto text-center space-y-3">
                <div class="w-12 h-12 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
                <p class="text-xs font-bold text-slate-400">Loading Battle Session #{{ sessionId }}...</p>
            </div>

            <!-- Countdown 3-2-1 Screen -->
            <div v-else-if="!isGameStarted && !matchResult" class="m-auto text-center bg-indigo-600 rounded-3xl p-10 w-full shadow-2xl animate-in zoom-in-95 duration-200">
                <p class="text-xs font-black text-indigo-200 tracking-widest uppercase">Battle Starting In</p>
                <h1 class="text-7xl font-black text-white mt-2 animate-bounce">{{ countdown }}</h1>
            </div>

            <!-- Live Quiz View -->
            <div v-else-if="questions.length > 0 && !isWaitingResult && !matchResult" class="my-auto space-y-6">
                <div class="flex justify-between items-center">
                    <span class="text-xs font-black text-indigo-400 tracking-wider">⚔️ MATCH ARENA</span>
                    <span :class="timer <= 3 ? 'bg-rose-500/20 text-rose-400 border-rose-500' : 'bg-indigo-500/20 text-indigo-300 border-indigo-500'" class="px-3 py-1 rounded-full text-xs font-black border">
                        ⏱️ {{ timer }}s
                    </span>
                </div>

                <div class="bg-slate-800/80 border border-slate-700/50 rounded-3xl p-6 space-y-4 shadow-xl">
                    <p class="text-[10px] font-black text-slate-400 tracking-widest uppercase">
                        QUESTION {{ currentIdx + 1 }} OF {{ questions.length }}
                    </p>
                    <h2 class="text-lg font-black text-white leading-snug">
                        {{ questions[currentIdx]?.question_text }}
                    </h2>

                    <!-- Options List -->
                    <div class="space-y-3 pt-2">
                        <button 
                            v-for="(text, key) in questions[currentIdx]?.options" 
                            :key="key"
                            @click="submitOption(key)"
                            :disabled="isLocked"
                            :class="selectedOption === key ? 'bg-indigo-600 border-indigo-400 text-white' : 'bg-slate-900 border-slate-700 text-slate-200 hover:border-slate-500'"
                            class="w-full p-4 rounded-2xl border text-left text-xs font-bold flex items-center justify-between transition-all"
                        >
                            <span>{{ text }}</span>
                            <span class="w-6 h-6 rounded-lg bg-slate-800 flex items-center justify-center text-[10px] font-black text-indigo-400 uppercase">
                                {{ key }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Waiting Result -->
            <div v-else-if="isWaitingResult && !matchResult" class="m-auto text-center space-y-4 bg-slate-800 border border-slate-700 p-8 rounded-3xl w-full">
                <div class="w-12 h-12 bg-emerald-500/20 text-emerald-400 rounded-full flex items-center justify-center text-xl font-bold mx-auto animate-pulse">
                    ✓
                </div>
                <h3 class="text-lg font-black text-white">Answers Submitted!</h3>
                <p class="text-xs text-slate-400">Waiting for your opponent to finish...</p>
            </div>

            <!-- Winner Modal -->
            <div v-if="matchResult" class="fixed inset-0 bg-slate-950/90 backdrop-blur-md z-50 flex items-center justify-center p-6">
                <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 text-center space-y-6 w-full max-w-xs shadow-2xl">
                    <div class="text-6xl">
                        {{ matchResult.winner_id === currentUser?.id ? '🏆' : matchResult.winner_id === null ? '🤝' : '💔' }}
                    </div>
                    <h2 class="text-2xl font-black text-white">
                        {{ matchResult.winner_id === currentUser?.id ? 'YOU WIN!' : matchResult.winner_id === null ? "IT'S A DRAW!" : 'YOU LOST!' }}
                    </h2>

                    <div class="flex justify-around bg-slate-950 p-4 rounded-2xl border border-slate-800">
                        <div>
                            <p class="text-[9px] font-black text-slate-500 uppercase">Player 1</p>
                            <p class="text-lg font-black text-indigo-400">{{ matchResult.player1_score ?? 0 }} pts</p>
                        </div>
                        <div class="w-px bg-slate-800"></div>
                        <div>
                            <p class="text-[9px] font-black text-slate-500 uppercase">Player 2</p>
                            <p class="text-lg font-black text-indigo-400">{{ matchResult.player2_score ?? 0 }} pts</p>
                        </div>
                    </div>

                    <button @click="goDashboard" class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl font-black text-xs shadow-xl transition-all">
                        Back to Dashboard 🚀
                    </button>
                </div>
            </div>

        </div>
    </div>
</template>