<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    project: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head :title="`${project.title} - Nalar`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <Link :href="route('projects.index')" class="text-sm text-indigo-600 hover:text-indigo-800 mb-1 inline-block">
                        &larr; Back to Projects
                    </Link>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">
                        {{ project.title }}
                    </h2>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                    <p class="text-gray-700">Project Shell</p>
                    <p v-if="project.target_completion_date" class="text-sm text-gray-500 mt-2">
                        Target Completion Date: {{ project.target_completion_date }}
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
