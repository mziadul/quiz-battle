<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    session: Object,
    currentUser: Object,
});

const countdown = ref(3);
const isGameStarted = ref(false);
const questions = ref([]);
const currentQuestionIndex = ref(0);
const selectedAnswer = ref(null);
const isAnswerLocked = ref(false);
const timer = ref(10); // 10 seconds per question
const isLoading = ref(true);
let timerInterval = null;
let resultCheckInterval = null;

const userAnswers = ref([]);
const isWaitingResult = ref(false);
const matchResult = ref(null);

const currentQuestion = computed(() => questions.value[currentQuestionIndex.value] || null);

onMounted(async () => {
    await fetchAndSyncGameState();

    if (window.Echo) {
        window.Echo.private(`game-session.${props.session.id}`)
            .listen('.QuizMatchFinished', (data) => {
                if (resultCheckInterval) clearInterval(resultCheckInterval);
                matchResult.value = data.results;
                isWaitingResult.value = false;
            });
    }
});

onUnmounted(() => {
    if (timerInterval) clearInterval(timerInterval);
    if (resultCheckInterval) clearInterval(resultCheckInterval);
    if (window.Echo) window.Echo.leave(`game-session.${props.session.id}`);
});

const fetchAndSyncGameState = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get(`/quiz/match/${props.session.id}/questions`);
        if (response.data.status === 'SUCCESS') {
            questions.value = response.data.questions || [];

            // If match is already completed, show results directly
            if (response.data.session_status === 'completed') {
                if (response.data.results) {
                    matchResult.value = response.data.results;
                } else {
                    isWaitingResult.value = true;
                }
                isLoading.value = false;
                return;
            }

            const startedAt = response.data.started_at ? new Date(response.data.started_at).getTime() : new Date().getTime();
            const serverTime = response.data.server_time ? new Date(response.data.server_time).getTime() : new Date().getTime();
            
            let elapsed = Math.floor((serverTime - startedAt) / 1000);
            if (isNaN(elapsed) || elapsed < 0) elapsed = 0;

            if (elapsed < 3) {
                countdown.value = Math.max(1, 3 - elapsed);
                startCountdown();
            } else {
                const activeMatchTime = elapsed - 3;
                const questionIdx = Math.floor(activeMatchTime / 10);
                const remainingQuestionTime = 10 - (activeMatchTime % 10);

                if (questionIdx < 5) {
                    currentQuestionIndex.value = questionIdx;
                    timer.value = remainingQuestionTime;
                    isGameStarted.value = true;
                    startQuestionTimer(true);
                } else {
                    // Total time expired
                    isGameStarted.value = true;
                    submitFinalAnswers();
                }
            }
        }
    } catch (err) {
        console.error('Failed to sync game state:', err);
    } finally {
        isLoading.value = false;
    }
};

const startCountdown = () => {
    const interval = setInterval(() => {
        countdown.value--;
        if (countdown.value <= 0) {
            clearInterval(interval);
            isGameStarted.value = true;
            startQuestionTimer(false);
        }
    }, 1000);
};

const startQuestionTimer = (isResume = false) => {
    if (!isResume) timer.value = 10;
    isAnswerLocked.value = false;
    selectedAnswer.value = null;

    if (timerInterval) clearInterval(timerInterval);

    timerInterval = setInterval(() => {
        timer.value--;
        if (timer.value <= 0) {
            handleAnswerSubmission(null, true);
        }
    }, 1000);
};

const submitAnswer = (optionKey) => {
    if (isAnswerLocked.value) return;

    isAnswerLocked.value = true;
    selectedAnswer.value = optionKey;

    handleAnswerSubmission(optionKey, false);
};

const handleAnswerSubmission = (optionKey, isTimeout = false) => {
    if (timerInterval) clearInterval(timerInterval);

    if (currentQuestion.value) {
        userAnswers.value.push({
            question_id: currentQuestion.value.id,
            selected_option: optionKey,
            time_taken: 10 - timer.value,
        });
    }

    setTimeout(() => {
        nextQuestion();
    }, isTimeout ? 0 : 300);
};

const nextQuestion = () => {
    if (currentQuestionIndex.value < questions.value.length - 1) {
        currentQuestionIndex.value++;
        startQuestionTimer(false);
    } else {
        if (timerInterval) clearInterval(timerInterval);
        submitFinalAnswers();
    }
};

const submitFinalAnswers = async () => {
    isWaitingResult.value = true;

    try {
        const response = await axios.post(`/quiz/match/${props.session.id}/submit`, {
            answers: userAnswers.value
        });

        if (response.data.status === 'COMPLETED') {
            matchResult.value = response.data.results;
            isWaitingResult.value = false;
        } else {
            // Polling fallback every 2.5s if socket misses
            startPollingForResults();
        }
    } catch (error) {
        console.error('Failed to submit answers:', error);
    }
};

const startPollingForResults = () => {
    if (resultCheckInterval) clearInterval(resultCheckInterval);

    resultCheckInterval = setInterval(async () => {
        try {
            const res = await axios.get(`/quiz/match/${props.session.id}/questions`);
            if (res.data.status === 'SUCCESS' && res.data.session_status === 'completed' && res.data.results) {
                clearInterval(resultCheckInterval);
                matchResult.value = res.data.results;
                isWaitingResult.value = false;
            }
        } catch (err) {
            console.error('Polling error:', err);
        }
    }, 2500);
};

const backToDashboard = () => {
    window.location.href = '/dashboard';
};
</script>

<template>
    <Head title="Match Arena - Quiz Battle" />

    <AuthenticatedLayout>
        <div class="py-12 max-w-4xl mx-auto px-4">
            
            <!-- LOADING STATE -->
            <div v-if="isLoading" class="bg-white p-12 text-center rounded-2xl shadow-lg">
                <div class="text-4xl animate-spin mb-3">⚔️</div>
                <p class="text-gray-600 font-semibold">Loading Synchronized Battle...</p>
            </div>

            <!-- 3-2-1 COUNTDOWN -->
            <div v-else-if="!isGameStarted" class="flex flex-col items-center justify-center h-96 bg-indigo-600 text-white rounded-2xl shadow-2xl">
                <p class="text-2xl font-bold uppercase tracking-wider opacity-80">Battle Starting In</p>
                <div class="text-8xl font-black mt-4 animate-bounce">
                    {{ countdown > 0 ? countdown : 'GO! ⚔️' }}
                </div>
            </div>

            <!-- LIVE MATCH ARENA -->
            <div v-else-if="currentQuestion && !isWaitingResult && !matchResult" class="bg-white p-8 rounded-2xl shadow-lg border border-gray-100">
                <div class="flex items-center justify-between pb-6 border-b">
                    <div class="text-center">
                        <p class="text-xs text-gray-500 uppercase">Player 1</p>
                        <p class="font-bold text-gray-800 text-lg">{{ session.player1?.name }}</p>
                    </div>

                    <div class="flex flex-col items-center">
                        <span class="text-2xl font-black text-indigo-600">VS</span>
                        <span :class="timer <= 3 ? 'bg-red-100 text-red-800 animate-pulse' : 'bg-amber-100 text-amber-800'" class="text-xs font-bold px-3 py-1 rounded-full mt-1">
                            ⏱️ {{ timer }}s
                        </span>
                    </div>

                    <div class="text-center">
                        <p class="text-xs text-gray-500 uppercase">Player 2</p>
                        <p class="font-bold text-gray-800 text-lg">{{ session.player2?.name }}</p>
                    </div>
                </div>

                <div class="mt-8">
                    <div class="flex justify-between text-xs text-gray-400 font-bold mb-2">
                        <span>QUESTION {{ currentQuestionIndex + 1 }} OF {{ questions.length }}</span>
                    </div>

                    <h2 class="text-xl font-extrabold text-gray-900 mb-6">
                        {{ currentQuestion.question_text }}
                    </h2>

                    <!-- OPTIONS GRID -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <button
                            v-for="opt in ['a', 'b', 'c', 'd']"
                            :key="opt"
                            @click="submitAnswer(opt)"
                            :disabled="isAnswerLocked"
                            :class="[
                                selectedAnswer === opt ? 'bg-indigo-600 text-white border-indigo-600 scale-[1.02]' : 'bg-gray-50 hover:bg-indigo-50 border-gray-200 text-gray-800',
                                isAnswerLocked && selectedAnswer !== opt ? 'opacity-50 cursor-not-allowed' : ''
                            ]"
                            class="p-4 border-2 rounded-xl text-left font-semibold transition-all flex items-center justify-between"
                        >
                            <span>{{ currentQuestion['option_' + opt] }}</span>
                            <span class="text-xs font-bold uppercase opacity-60">{{ opt.toUpperCase() }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- WAITING FOR OPPONENT WITH CLEAR FEEDBACK -->
            <div v-if="isWaitingResult && !matchResult" class="bg-white p-12 text-center rounded-2xl shadow-lg border border-indigo-50">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 text-green-600 rounded-full text-3xl mb-4 animate-bounce">
                    ✓
                </div>
                <h3 class="text-2xl font-bold text-gray-800">Your Answers Submitted Safely!</h3>
                <p class="text-gray-500 mt-2 text-sm">
                    All 5 questions recorded. Waiting for your opponent to finish their quiz...
                </p>

                <div class="mt-8 flex justify-center items-center gap-2 text-xs font-semibold text-gray-400">
                    <span class="w-2 h-2 bg-indigo-600 rounded-full animate-ping"></span>
                    Live Sync Active
                </div>
            </div>

            <!-- 🏆 WINNER MODAL ANNOUNCEMENT -->
            <div v-if="matchResult" class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50">
                <div class="bg-white rounded-2xl p-8 max-w-md w-full text-center shadow-2xl">
                    <div class="text-6xl mb-3">
                        {{ matchResult.winner_id === currentUser.id ? '🏆' : (matchResult.winner_id ? '💔' : '🤝') }}
                    </div>
                    
                    <h2 class="text-3xl font-black text-gray-900">
                        {{ matchResult.winner_id === currentUser.id ? 'YOU WIN!' : (matchResult.winner_id ? 'YOU LOST!' : "IT'S A DRAW!") }}
                    </h2>

                    <div class="my-6 bg-gray-50 p-4 rounded-xl flex justify-around border">
                        <div>
                            <p class="text-xs text-gray-400 font-bold uppercase">{{ session.player1?.name }}</p>
                            <p class="text-2xl font-extrabold text-indigo-600">{{ matchResult.p1_score }} pts</p>
                        </div>
                        <div class="border-r border-gray-200"></div>
                        <div>
                            <p class="text-xs text-gray-400 font-bold uppercase">{{ session.player2?.name }}</p>
                            <p class="text-2xl font-extrabold text-indigo-600">{{ matchResult.p2_score }} pts</p>
                        </div>
                    </div>

                    <button @click="backToDashboard" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 rounded-xl shadow-lg transition-all">
                        Back to Dashboard 🚀
                    </button>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>