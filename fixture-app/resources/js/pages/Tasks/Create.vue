<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import type { Team, User } from '@data/app/models';

defineProps<{
    teams: Team[];
    assignees: User[];
}>();

const form = useForm({
    team_id: null as number | null,
    title: '',
    description: '',
    status: 'todo',
    assignee_id: null as number | null,
    due_at: null as string | null,
});

function submit() {
    form.post('/tasks');
}
</script>

<template>
    <form class="mx-auto max-w-lg space-y-4 p-6" @submit.prevent="submit">
        <h1 class="text-xl font-semibold">New task</h1>

        <label class="block">
            <span class="text-sm">Team</span>
            <select v-model="form.team_id" class="mt-1 w-full rounded border p-2">
                <option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
            </select>
        </label>

        <label class="block">
            <span class="text-sm">Title</span>
            <input v-model="form.title" class="mt-1 w-full rounded border p-2" />
            <span v-if="form.errors.title" class="text-xs text-red-600">{{ form.errors.title }}</span>
        </label>

        <label class="block">
            <span class="text-sm">Description</span>
            <textarea v-model="form.description" class="mt-1 w-full rounded border p-2"></textarea>
        </label>

        <label class="block">
            <span class="text-sm">Status</span>
            <select v-model="form.status" class="mt-1 w-full rounded border p-2">
                <option value="todo">To do</option>
                <option value="in_progress">In progress</option>
                <option value="done">Done</option>
            </select>
        </label>

        <label class="block">
            <span class="text-sm">Assignee</span>
            <select v-model="form.assignee_id" class="mt-1 w-full rounded border p-2">
                <option :value="null">Unassigned</option>
                <option v-for="user in assignees" :key="user.id" :value="user.id">{{ user.name }}</option>
            </select>
        </label>

        <label class="block">
            <span class="text-sm">Due</span>
            <input v-model="form.due_at" type="date" class="mt-1 w-full rounded border p-2" />
        </label>

        <button type="submit" :disabled="form.processing" class="rounded bg-blue-600 px-3 py-1.5 text-sm text-white">Create</button>
    </form>
</template>
