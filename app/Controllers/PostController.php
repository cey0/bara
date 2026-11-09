<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Post;
use App\Models\User;
use Framework\Http\Request;
use Framework\Http\Response;

final class PostController
{
    public function index(): Response
    {
        return view('posts/index', [
            'title' => 'Tulisan',
            'posts' => Post::query()->orderBy('created_at', 'desc')->get(),
        ]);
    }

    public function show(string $post): Response
    {
        $post = Post::findOrFail($post);

        return view('posts/show', [
            'title' => $post->title,
            'post' => $post,
            'comments' => $post->comments,
        ]);
    }

    public function create(): Response
    {
        return view('posts/create', [
            'title' => 'Tulis baru',
            'users' => User::all(),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = [
            'title' => trim((string) $request->input('title', '')),
            'body' => trim((string) $request->input('body', '')),
            'user_id' => (int) $request->input('user_id', 0),
        ];

        $error = $this->validate($data);

        if ($error !== null) {
            return view('posts/create', [
                'title' => 'Tulis baru',
                'users' => User::all(),
                'error' => $error,
                'old' => $data,
            ]);
        }

        $post = Post::create($data);

        return redirect('/posts/' . $post->id);
    }

    public function destroy(string $post): Response
    {
        Post::findOrFail($post)->delete();

        return redirect('/posts');
    }

    /**
     * Minimal validation at the trust boundary: required fields, length, and
     * a real author. Returns the first error message or null.
     */
    private function validate(array $data): ?string
    {
        if ($data['title'] === '') {
            return 'Judul wajib diisi.';
        }

        if (mb_strlen($data['title']) > 120) {
            return 'Judul maksimal 120 karakter.';
        }

        if ($data['body'] === '') {
            return 'Isi tulisan wajib diisi.';
        }

        if (User::find($data['user_id']) === null) {
            return 'Penulis tidak ditemukan.';
        }

        return null;
    }
}
