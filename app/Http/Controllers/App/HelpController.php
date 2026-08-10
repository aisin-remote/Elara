<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\SupportArticle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HelpController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q'));
        $articles = SupportArticle::published()
            ->when(mb_strlen($query) >= 2, fn (Builder $builder) => $builder->where(fn (Builder $search) => $search
                ->where('title', 'like', '%'.addcslashes($query, '%_\\').'%')
                ->orWhere('body', 'like', '%'.addcslashes($query, '%_\\').'%')))
            ->orderBy('category')
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString();

        return view('app.help.index', [
            'articles' => $articles,
            'query' => $query,
            'faqs' => SupportArticle::published()->where('category', 'FAQ')->orderBy('title')->limit(6)->get(),
            'tickets' => $request->user()->supportTickets()->latest()->limit(5)->get(),
        ]);
    }

    public function show(SupportArticle $article): View
    {
        abort_unless($article->is_published, 404);

        return view('app.help.show', ['article' => $article]);
    }
}
