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
let countdownInterval = null;
let questionStartTime = 0; // 🔑 উত্তর দেয়ার সঠিক সময় পরিমাপের জন্য

onMounted(() => {
    const token = localStorage.getItem('auth_token');
    const storedUser = localStorage.getItem('user_data');
    
    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
    
    if (storedUser) {
        try {
            currentUser.value = JSON.parse(storedUser);
        } catch (e) {
            console.error("User data parse error", e);
        }
    }

    if (window.Echo && props.sessionId) {
        window.Echo.private(`game-session.${props.sessionId}`)
            .listen('.QuizMatchFinished', (e) => {
                stopPolling();
                if (e && e.results) {
                    matchResult.value = e.results;
                    isWaitingResult.value = false;
                }
            });
    }

    fetchAndSyncMatch();
});

// 1. Fetch Questions & Sync State
const fetchAndSyncMatch = async () => {
    isLoading.value = true;
    try {
        const token = localStorage.getItem('auth_token');
        if (token) {
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        }

        const res = await axios.get(`/api/quiz/questions/${props.sessionId}`);
        
        if (res.data.status === 'SUCCESS') {
            if (res.data.is_waiting || res.data.session_status === 'waiting' || res.data.session_status === 'pending') {
                isWaitingResult.value = true;
                startPollingForOpponent();
                return;
            }

            stopPolling();
            isWaitingResult.value = false;

            if (res.data.session_status === 'completed' && res.data.results) {
                matchResult.value = res.data.results;
                return;
            }

            questions.value = res.data.questions || [];

            const serverIndex = res.data.current_index ?? 0;
            const remaining = res.data.remaining_seconds ?? 10;

            if (serverIndex === 0 && remaining >= 9 && !isGameStarted.value) {
                startCountdown();
            } else {
                currentIdx.value = serverIndex;
                timer.value = remaining;
                isGameStarted.value = true;
                startQuestionTimer(true, remaining);
            }
        }
    } catch (err) {
        console.error('Fetch Questions Error:', err.response?.data || err.message);
    } finally {
        isLoading.value = false;
    }
};

// 🔄 অপোনেন্ট জয়েন বা চ্যালেঞ্জ একসেপ্ট করার জন্য পোলিং
const startPollingForOpponent = () => {
    if (pollingInterval) return;

    pollingInterval = setInterval(async () => {
        try {
            const res = await axios.get(`/api/quiz/questions/${props.sessionId}`);
            if (res.data.status === 'SUCCESS' && !res.data.is_waiting && res.data.session_status === 'active') {
                stopPolling();
                fetchAndSyncMatch();
            }
        } catch (err) {
            console.error('Polling Opponent Error:', err);
        }
    }, 1500);
};

// 2. Countdown 3-2-1 Sequence
const startCountdown = () => {
    if (countdownInterval) clearInterval(countdownInterval);

    countdown.value = 3;
    isGameStarted.value = false;

    countdownInterval = setInterval(() => {
        if (countdown.value > 1) {
            countdown.value -= 1;
        } else {
            clearInterval(countdownInterval);
            countdownInterval = null;
            isGameStarted.value = true;
            startQuestionTimer(false, 10);
        }
    }, 1000);
};

// 3. Question Timer
const startQuestionTimer = (isResume = false, initialTime = 10) => {
    isLocked.value = false;
    selectedOption.value = null;
    timer.value = initialTime;
    questionStartTime = Date.now();

    if (timerInterval) clearInterval(timerInterval);

    timerInterval = setInterval(() => {
        if (timer.value <= 1) {
            clearInterval(timerInterval);
            submitOption(null, true);
        } else {
            timer.value -= 1;
        }
    }, 1000);
};

// 4. Submit Option
const submitOption = (optKey, isTimeout = false) => {
    if (isLocked.value) return;
    isLocked.value = true;
    selectedOption.value = optKey;

    if (timerInterval) clearInterval(timerInterval);

    // 🔑 সময় হিসেব করার সঠিক পদ্ধতি (পরিমাপ করার সময়)
    const timeTaken = Math.min(10, Math.round((Date.now() - questionStartTime) / 1000));

    const currentQ = questions.value[currentIdx.value];
    if (currentQ) {
        userAnswers.value.push({
            question_id: currentQ.id,
            selected_option: optKey,
            time_taken: timeTaken
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

const startPollingResult = () => {
    stopPolling();
    pollingInterval = setInterval(async () => {
        try {
            const res = await axios.get(`/api/quiz/questions/${props.sessionId}`);
            if (res.data.status === 'SUCCESS' && res.data.session_status === 'completed') {
                stopPolling();
                matchResult.value = res.data.results;
                isWaitingResult.value = false;
            }
        } catch (err) {
            console.error('Polling Result Error:', err);
        }
    }, 2500);
};

const stopPolling = () => {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
};

const goDashboard = () => {
    router.visit('/app/dashboard');
};

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
    if (countdownInterval) clearInterval(countdownInterval);
    stopPolling();
    if (window.Echo && props.sessionId) {
        window.Echo.leave(`game-session.${props.sessionId}`);
    }
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

            <!-- Waiting for Opponent Screen -->
            <div v-else-if="isWaitingResult && !matchResult" class="m-auto text-center space-y-4 bg-slate-800 border border-slate-700 p-8 rounded-3xl w-full shadow-2xl animate-in zoom-in-95 duration-200">
                <div class="w-12 h-12 bg-indigo-500/20 text-indigo-400 rounded-full flex items-center justify-center text-xl font-bold mx-auto animate-pulse">
                    ⚔️
                </div>
                <h3 class="text-lg font-black text-white">Waiting for Opponent...</h3>
                <p class="text-xs text-slate-400">Waiting for second player to accept or join the game.</p>
            </div>

            <!-- Countdown 3-2-1 Screen -->
            <div v-else-if="!isGameStarted && !matchResult" class="m-auto text-center bg-indigo-600 rounded-3xl p-10 w-full shadow-2xl animate-in zoom-in-95 duration-200">
                <p class="text-xs font-black text-indigo-200 tracking-widest uppercase">Battle Starting In</p>
                <h1 class="text-7xl font-black text-white mt-2 animate-bounce">{{ countdown }}</h1>
            </div>

            <!-- Live Quiz View -->
            <div v-else-if="questions.length > 0 && !matchResult" class="my-auto space-y-6">
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