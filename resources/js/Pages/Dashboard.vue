<template>
    <div style="padding: 40px; font-family: sans-serif;">
        <h2>⚡ QuizBattle Reverb + Echo Real-time Test</h2>
        
        <div style="margin-top: 20px; padding: 15px; border: 1px solid #ccc; border-radius: 8px;">
            <h3>Received Messages Log:</h3>
            <p v-if="messages.length === 0" style="color: gray;">
                Waiting for real-time events... (Open <code>/fire-event</code> in another tab)
            </p>

            <ul v-else>
                <li v-for="(msg, index) in messages" :key="index" style="margin-bottom: 8px;">
                    <strong>[{{ msg.time }}]:</strong> {{ msg.message }}
                </li>
            </ul>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const messages = ref([]);

onMounted(() => {
    console.log('🔌 Connecting to Laravel Reverb via Echo...');

    // test-channel লিসেন করা হচ্ছে
    window.Echo.channel('test-channel')
        .listen('.TestMessageEvent', (data) => {
            console.log('🎉 Event Received in Real-time:', data);
            messages.value.unshift({
                message: data.message,
                time: data.time
            });
        });
});

onUnmounted(() => {
    window.Echo.leaveChannel('test-channel');
});
</script>