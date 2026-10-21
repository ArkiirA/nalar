<script setup>
import { nextTick, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
    chapter: {
        type: Object,
        default: null,
    },
});

const messages = ref([
    {
        sender: 'hermes',
        text: 'Halo! Saya Hermes Copilot. Saya siap mendiskusikan rumusan masalah, memeriksa draf kalimat akademik, atau memberi masukan metodologi bab skripsi Anda.',
        time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
    },
]);

const inputPrompt = ref('');
const isLoading = ref(false);
const includeChapterContext = ref(true);
const chatContainer = ref(null);
const copySuccessId = ref(null);

const scrollToBottom = async () => {
    await nextTick();
    if (chatContainer.value) {
        chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
};

const extractPlainText = (content) => {
    if (!content) return '';
    try {
        const parts = [];
        const traverse = (node) => {
            if (node.text) parts.push(node.text);
            if (node.content && Array.isArray(node.content)) {
                node.content.forEach(traverse);
            }
        };
        traverse(content);
        return parts.join(' ').trim();
    } catch {
        return '';
    }
};

const sendMessage = async (customText = null) => {
    const textToSend = customText || inputPrompt.value.trim();
    if (!textToSend || isLoading.value) return;

    messages.value.push({
        sender: 'user',
        text: textToSend,
        time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
    });

    if (!customText) {
        inputPrompt.value = '';
    }
    scrollToBottom();

    isLoading.value = true;

    try {
        const payload = {
            message: textToSend,
            chapter_id: props.chapter?.id || null,
        };

        if (includeChapterContext.value && props.chapter?.content) {
            payload.chapter_text = extractPlainText(props.chapter.content);
        }

        const response = await axios.post(
            route('projects.hermes.chat', props.project.id),
            payload
        );

        messages.value.push({
            sender: 'hermes',
            text: response.data.reply || 'Tidak ada balasan dari Hermes.',
            time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        });
    } catch (e) {
        const errorMsg = e.response?.data?.error || 'Gagal tersambung ke Hermes Agent.';
        messages.value.push({
            sender: 'hermes',
            text: `⚠️ ${errorMsg}`,
            time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
            isError: true,
        });
    } finally {
        isLoading.value = false;
        scrollToBottom();
    }
};

const copyToClipboard = async (text, id) => {
    try {
        await navigator.clipboard.writeText(text);
        copySuccessId.value = id;
        setTimeout(() => {
            copySuccessId.value = null;
        }, 2000);
    } catch (e) {
        console.error('Failed to copy text:', e);
    }
};

const quickPrompts = [
    'Bantu tajamkan rumusan masalah',
    'Review tata bahasa akademik teks ini',
    'Cari kelemahan argumen di draf ini',
];
</script>

<template>
    <div class="flex flex-col h-[calc(100vh-140px)] text-xs text-stone-800">
        <!-- Plugin Header Badge -->
        <div class="p-3 bg-gradient-to-r from-stone-900 to-stone-800 text-white rounded-lg shadow-xs mb-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-md bg-emerald-500/20 border border-emerald-400/40 flex items-center justify-center text-emerald-300 font-mono font-bold text-xs">
                    H
                </div>
                <div>
                    <h3 class="font-semibold text-stone-100 leading-tight">Hermes Copilot</h3>
                    <p class="text-[10px] text-stone-400">Agent v0.22 (Autonomous Academic Sparring)</p>
                </div>
            </div>
            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                Active
            </span>
        </div>

        <!-- Active Context Switch -->
        <div class="mb-2 p-2 bg-stone-50 border border-stone-200 rounded-md flex items-center justify-between text-[11px]">
            <div class="flex items-center gap-1.5 truncate">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                <span class="text-stone-500 shrink-0">Konteks:</span>
                <span class="font-medium text-stone-800 truncate" :title="chapter?.title || 'Seluruh Dokumen'">
                    {{ chapter?.title || 'Belum memilih bab' }}
                </span>
            </div>
            <label class="flex items-center gap-1 cursor-pointer shrink-0 text-stone-500 hover:text-stone-700">
                <input
                    type="checkbox"
                    v-model="includeChapterContext"
                    class="rounded border-stone-300 text-emerald-600 focus:ring-emerald-500 h-3 w-3"
                />
                <span class="text-[10px]">Kirim Draf</span>
            </label>
        </div>

        <!-- Chat History Messages Stage -->
        <div ref="chatContainer" class="flex-1 overflow-y-auto space-y-3 p-1 pr-1.5">
            <div
                v-for="(msg, idx) in messages"
                :key="idx"
                class="flex flex-col"
                :class="msg.sender === 'user' ? 'items-end' : 'items-start'"
            >
                <div
                    class="max-w-[92%] p-3 rounded-lg border leading-relaxed"
                    :class="msg.sender === 'user'
                        ? 'bg-emerald-600 text-white border-emerald-600 rounded-tr-none'
                        : msg.isError
                            ? 'bg-rose-50 text-rose-800 border-rose-200 rounded-tl-none'
                            : 'bg-white text-stone-800 border-stone-200 shadow-2xs rounded-tl-none'"
                >
                    <div class="whitespace-pre-line text-xs font-sans">
                        {{ msg.text }}
                    </div>

                    <div
                        class="flex items-center justify-between gap-2 mt-1.5 pt-1 text-[10px]"
                        :class="msg.sender === 'user' ? 'text-emerald-100 border-t border-emerald-500/30' : 'text-stone-400 border-t border-stone-100'"
                    >
                        <span>{{ msg.time }}</span>
                        <button
                            v-if="msg.sender === 'hermes' && !msg.isError"
                            type="button"
                            @click="copyToClipboard(msg.text, idx)"
                            class="hover:text-stone-700 transition inline-flex items-center gap-0.5"
                            title="Salin teks"
                        >
                            <span>{{ copySuccessId === idx ? '✓ Tersalin' : 'Salin' }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="isLoading" class="flex items-center gap-2 text-stone-400 p-2">
                <div class="animate-spin w-3.5 h-3.5 border-2 border-stone-400 border-t-transparent rounded-full"></div>
                <span class="text-[11px]">Hermes sedang menganalisis...</span>
            </div>
        </div>

        <!-- Quick Action Chips -->
        <div class="py-2 flex items-center gap-1.5 overflow-x-auto no-scrollbar shrink-0">
            <button
                v-for="(prompt, pIdx) in quickPrompts"
                :key="pIdx"
                type="button"
                :disabled="isLoading"
                @click="sendMessage(prompt)"
                class="px-2 py-1 bg-stone-100 hover:bg-stone-200 border border-stone-200 rounded text-[10px] text-stone-600 hover:text-stone-900 whitespace-nowrap transition"
            >
                {{ prompt }}
            </button>
        </div>

        <!-- Chat Input Form -->
        <div class="pt-2 border-t border-stone-200 shrink-0">
            <form @submit.prevent="sendMessage()" class="flex gap-1.5">
                <input
                    type="text"
                    v-model="inputPrompt"
                    :disabled="isLoading"
                    placeholder="Tanya Hermes tentang draf bab ini..."
                    class="flex-1 rounded-md border-stone-300 text-xs focus:border-emerald-500 focus:ring-emerald-500 px-3 py-2 disabled:bg-stone-100"
                />
                <button
                    type="submit"
                    :disabled="!inputPrompt.trim() || isLoading"
                    class="px-3 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 text-white rounded-md font-medium text-xs transition shrink-0"
                >
                    Kirim
                </button>
            </form>
        </div>
    </div>
</template>
