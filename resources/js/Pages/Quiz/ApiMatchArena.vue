<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    sessionId: Number,
});

const token = ref('');
const user = ref(null);
const questions = ref([]);
const currentQuestionIndex = ref(0);
const selectedOption = ref(null);
const isOptionLocked = ref(false); // 🔒 লক প্রপার্টি
const userAnswers = ref([]);
const timer = ref(10);
let timerInterval = null;

const gameState = ref('LOADING');
const countdown = ref(3);
const matchResults = ref(null);

const getApiClient = () => {
    return axios.create({
        headers: {
            'Authorization': `Bearer ${token.value}`,
            'Accept': 'application/json'
        }
    });
};

onMounted(() => {
    token.value = localStorage.getItem('auth_token');
    const storedUser = localStorage.getItem('user_data');

    if (!token.value) {
        window.location.href = '/app/login';
        return;
    }

    if (storedUser) {
        user.value = JSON.parse(storedUser);
    }

    loadMatchData();

    if (window.Echo && user.value) {
        window.Echo.connector.options.auth.headers.Authorization = `Bearer ${token.value}`;

        window.Echo.private(`game-session.${props.sessionId}`)
            .listen('.QuizMatchFinished', (data) => {
                console.log('--- REALTIME MATCH FINISHED EVENT RECEIVED ---', data);
                matchResults.value = data.results;
                gameState.value = 'FINISHED';
            });
    }
});

onUnmounted(() => {
    clearInterval(timerInterval);
});

const loadMatchData = async () => {
    try {
        const client = getApiClient();
        const response = await client.get(`/api/quiz/questions/${props.sessionId}`);
        
        if (response.data.status === 'SUCCESS') {
            if (response.data.session_status === 'completed') {
                matchResults.value = response.data.results;
                gameState.value = 'FINISHED';
            } else {
                questions.value = response.data.questions;
                
                // 🔑 রিফ্রেশ দেওয়া হলে ব্যাকএন্ডের ইনডেক্স ও অবশিষ্ট টাইমার থেকে কন্টিনিউ হবে
                if (response.data.current_index !== undefined) {
                    currentQuestionIndex.value = response.data.current_index;
                    timer.value = response.data.remaining_seconds;
                    
                    gameState.value = 'PLAYING';
                    startQuestionTimer(response.data.remaining_seconds);
                } else {
                    startMatchCountdown();
                }
            }
        }
    } catch (error) {
        console.error('Error loading match:', error);
    }
};

// ⏱️ টাইমার ফাংশনে কাস্টম রিমেইনিং সেকেন্ড এক্সেপ্ট করা
const startQuestionTimer = (initialTimer = 10) => {
    clearInterval(timerInterval);
    timer.value = initialTimer;
    selectedOption.value = null;
    isOptionLocked.value = false;

    timerInterval = setInterval(() => {
        timer.value--;
        if (timer.value <= 0) {
            clearInterval(timerInterval);
            handleNextQuestion();
        }
    }, 1000);
};

const startMatchCountdown = () => {
    gameState.value = 'COUNTDOWN';
    const interval = setInterval(() => {
        countdown.value--;
        if (countdown.value <= 0) {
            clearInterval(interval);
            gameState.value = 'PLAYING';
            startQuestionTimer();
        }
    }, 1000);
};

// 🎯 উত্তর সিলেক্ট করলে সাথে সাথে লক + ১ সেকেন্ড পর অটো নেক্সট
const selectAnswer = (optionKey) => {
    if (isOptionLocked.value) return; // লক করা থাকলে পরিবর্তন করা যাবে না

    selectedOption.value = optionKey;
    isOptionLocked.value = true; // লক করা হলো

    clearInterval(timerInterval);

    setTimeout(() => {
        handleNextQuestion();
    }, 600); // 0.6 সেকেন্ড ওয়েট করে অটোমেটিক পরবর্তী প্রশ্ন
};

const handleNextQuestion = () => {
    clearInterval(timerInterval);
    const currentQ = questions.value[currentQuestionIndex.value];

    userAnswers.value.push({
        question_id: currentQ.id,
        selected_option: selectedOption.value,
        time_taken: 10 - timer.value
    });

    if (currentQuestionIndex.value < questions.value.length - 1) {
        currentQuestionIndex.value++;
        startQuestionTimer();
    } else {
        submitAllAnswers();
    }
};

const submitAllAnswers = async () => {
    gameState.value = 'SUBMITTING';
    try {
        const client = getApiClient();
        
        // 🚀 POST Request for submitting answers
        const response = await client.post(`/api/quiz/submit-answers/${props.sessionId}`, {
            answers: userAnswers.value
        });

        if (response.data.status === 'COMPLETED') {
            matchResults.value = response.data.results;
            gameState.value = 'FINISHED'; // 👈 কোনো রিডাইরেক্ট ছাড়া সোজাসুজি রেজাল্ট স্ক্রিন দেখাবে
        } else {
            gameState.value = 'WAITING_OPPONENT';
        }
    } catch (error) {
        console.error('Failed to submit answers:', error);
    }
};
</script>

<template>
    <div class="min-h-screen bg-slate-900 text-slate-100 p-6 flex flex-col justify-center items-center">
        <div class="max-w-2xl w-full">
            
            <div v-if="gameState === 'COUNTDOWN'" class="bg-slate-800 border border-slate-700 p-12 rounded-3xl text-center shadow-2xl space-y-4">
                <h2 class="text-xl font-bold text-slate-300">Get Ready! Match Starting In</h2>
                <div class="text-7xl font-black text-indigo-500 animate-ping">{{ countdown }}</div>
            </div>

            <div v-else-if="gameState === 'PLAYING'" class="bg-slate-800 border border-slate-700 p-8 rounded-3xl shadow-2xl space-y-6">
                <div class="flex justify-between items-center border-b border-slate-700 pb-4">
                    <span class="text-xs font-bold text-slate-400 uppercase">Question {{ currentQuestionIndex + 1 }} / {{ questions.length }}</span>
                    <span :class="timer <= 3 ? 'text-red-400 font-black animate-pulse' : 'text-indigo-400 font-bold'" class="text-lg">
                        ⏱️ {{ timer }}s
                    </span>
                </div>

                <h3 class="text-xl font-black text-white">
                    {{ questions[currentQuestionIndex]?.question_text }}
                </h3>

                <!-- Question Options Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <button
                        v-for="(text, key) in questions[currentQuestionIndex]?.options"
                        :key="key"
                        @click="selectAnswer(key)"
                        :disabled="isOptionLocked"
                        :class="[
                            selectedOption === key ? 'bg-indigo-600 text-white border-indigo-500 scale-105' : 'bg-slate-900 text-slate-200 border-slate-700 hover:border-indigo-500',
                            isOptionLocked ? 'cursor-not-allowed opacity-80' : 'cursor-pointer'
                        ]"
                        class="p-4 rounded-xl border-2 font-bold text-left transition-all flex items-center gap-3"
                    >
                        <span class="w-8 h-8 rounded-lg bg-slate-800 text-indigo-400 flex items-center justify-center font-black text-sm">{{ key }}</span>
                        <span>{{ text }}</span>
                    </button>
                </div>
            </div>

            <div v-else-if="gameState === 'WAITING_OPPONENT' || gameState === 'SUBMITTING'" class="bg-slate-800 border border-slate-700 p-12 rounded-3xl text-center shadow-2xl space-y-4">
                <div class="text-5xl animate-spin">⏳</div>
                <h3 class="text-xl font-bold text-white">Answers Submitted!</h3>
                <p class="text-xs text-slate-400">Waiting for opponent to finish their quiz...</p>
            </div>

            <!-- Match Results View -->
            <div v-else-if="gameState === 'FINISHED'" class="bg-slate-800 border border-slate-700 p-8 rounded-3xl text-center shadow-2xl space-y-6">
                <div class="text-6xl">
                    <span v-if="matchResults?.winner_id === user?.id">🏆</span>
                    <span v-else-if="matchResults?.winner_id === null">🤝</span>
                    <span v-else>💔</span>
                </div>

                <div>
                    <h2 class="text-3xl font-black">
                        <span v-if="matchResults?.winner_id === user?.id" class="text-emerald-400">You Won! 🎉</span>
                        <span v-else-if="matchResults?.winner_id === null" class="text-amber-400">It's a Draw! 🤝</span>
                        <span v-else class="text-rose-500">You Lost! 💔</span>
                    </h2>

                    <!-- Points Summary -->
                    <div class="mt-4 p-4 bg-slate-900 rounded-2xl flex justify-around text-sm font-bold">
                        <div>
                            <p class="text-slate-400 text-xs">Player 1 Score</p>
                            <p class="text-indigo-400 text-lg">{{ matchResults?.player1_score ?? 0 }} pts</p>
                        </div>
                        <div>
                            <p class="text-slate-400 text-xs">Player 2 Score</p>
                            <p class="text-indigo-400 text-lg">{{ matchResults?.player2_score ?? 0 }} pts</p>
                        </div>
                    </div>
                </div>

                <a href="/app/dashboard" class="inline-block bg-indigo-600 text-white px-6 py-3 rounded-xl font-bold shadow-lg hover:bg-indigo-500 transition-all text-sm">
                    Back to Dashboard
                </a>
            </div>

        </div>
    </div>
</template>