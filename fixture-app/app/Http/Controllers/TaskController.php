<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Tasks/Index', [
            'tasks' => Task::query()->with('assignee')->latest()->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Tasks/Create', [
            'teams' => Team::query()->orderBy('name')->get(),
            'assignees' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        Task::query()->create($request->validated());

        return to_route('tasks.index')->with('success', 'Task created.');
    }

    public function edit(Task $task): Response
    {
        return Inertia::render('Tasks/Edit', [
            'task' => $task->load('assignee'),
            'assignees' => User::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $task->update($request->validated());

        return to_route('tasks.index')->with('success', 'Task updated.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $task->delete();

        return to_route('tasks.index')->with('success', 'Task deleted.');
    }
}
