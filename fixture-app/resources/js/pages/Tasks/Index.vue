<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Task, TaskRelations } from '@data/app/models';

defineProps<{
    tasks: (Task & Pick<TaskRelations, 'assignee'>)[];
}>();
</script>

<template>
    <div class="mx-auto max-w-4xl p-6">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold">Tasks</h1>
            <Link href="/tasks/create" class="rounded bg-blue-600 px-3 py-1.5 text-sm text-white">New task</Link>
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500">
                    <th class="py-2">Title</th>
                    <th class="py-2">Status</th>
                    <th class="py-2">Assignee</th>
                    <th class="py-2">Due</th>
                    <th class="py-2"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="task in tasks" :key="task.id" class="border-t">
                    <td class="py-2">{{ task.title }}</td>
                    <td class="py-2">{{ task.status }}</td>
                    <td class="py-2">{{ task.assignee?.name ?? 'Unassigned' }}</td>
                    <td class="py-2">{{ task.due_at ?? '—' }}</td>
                    <td class="py-2 text-right">
                        <Link :href="`/tasks/${task.id}/edit`" class="text-blue-600">Edit</Link>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
