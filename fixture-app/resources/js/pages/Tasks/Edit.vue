<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import type { Task, TaskRelations, User } from '@data/app/models';

const props = defineProps<{
    task: Task & Pick<TaskRelations, 'assignee'>;
    assignees: User[];
}>();

const form = useForm({
    title: props.task.title,
    description: props.task.description ?? '',
    status: props.task.status,
    assignee_id: props.task.assignee_id,
    due_at: props.task.due_at,
});

function submit() {
    // TODO: submit the update
}

function destroy() {
    // TODO: delete the task and go back to the list
}
</script>

<template>
    <form class="mx-auto max-w-lg space-y-4 p-6" @submit.prevent="submit">
        <h1 class="text-xl font-semibold">Edit task</h1>

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

        <div class="flex items-center gap-3">
            <button type="submit" :disabled="form.processing" class="rounded bg-blue-600 px-3 py-1.5 text-sm text-white">Save</button>
            <button type="button" class="text-sm text-red-600" @click="destroy">Delete</button>
        </div>
    </form>
</template>
