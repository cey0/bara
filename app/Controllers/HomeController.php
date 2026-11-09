<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Framework\Http\Response;

final class HomeController
{
    public function index(): Response
    {
        return view('home', [
            'title' => 'Beranda',
            'postCount' => Post::query()->count(),
            'userCount' => User::query()->count(),
            'commentCount' => Comment::query()->count(),
            'latest' => Post::query()->orderBy('created_at', 'desc')->limit(3)->get(),
        ]);
    }
}
