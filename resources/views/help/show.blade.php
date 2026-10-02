<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $article->title }} - Odden Help Center</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex flex-col">

    <!-- Header Navigation -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('odden.help.index') }}" class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold text-lg shadow-sm">F</span>
                    <span class="font-bold text-lg text-slate-900 tracking-tight">Odden Help Center</span>
                </a>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('odden.support.create') }}" class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition shadow-sm">
                    Submit a Ticket
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-5xl mx-auto w-full px-4 sm:px-6 py-8">
        
        <!-- Breadcrumbs -->
        <nav class="flex items-center gap-2 text-xs text-slate-500 mb-6 font-medium">
            <a href="{{ route('odden.help.index') }}" class="hover:text-indigo-600 transition">Help Center</a>
            <span>/</span>
            <a href="{{ route('odden.help.index', ['category' => $article->category]) }}" class="hover:text-indigo-600 transition">{{ $article->category }}</a>
            <span>/</span>
            <span class="text-slate-800 truncate max-w-xs">{{ $article->title }}</span>
        </nav>

        <article class="bg-white rounded-2xl border border-slate-200 p-8 sm:p-12 shadow-sm">
            <!-- Article Header -->
            <div class="border-b border-slate-100 pb-6 mb-8">
                <div class="flex items-center gap-2 mb-3">
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700">{{ $article->category }}</span>
                    <span class="text-xs text-slate-400">• Updated {{ $article->updated_at?->diffForHumans() }}</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight mb-3">
                    {{ $article->title }}
                </h1>
                <div class="text-xs text-slate-400 flex items-center gap-4">
                    <span>Written by {{ $article->author?->name ?? 'Support Team' }}</span>
                    <span>{{ $article->views_count }} views</span>
                </div>
            </div>

            <!-- Article Body -->
            <div class="prose prose-slate max-w-none text-slate-700 leading-relaxed space-y-4">
                {!! nl2br(e($article->body)) !!}
            </div>

            <!-- Feedback / Voting Section -->
            <div class="mt-12 pt-8 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4 bg-slate-50 -mx-8 -mb-8 p-8 rounded-b-2xl">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 mb-0.5">Was this article helpful?</h3>
                    <p class="text-xs text-slate-500">{{ $article->helpful_count }} people found this helpful</p>
                </div>

                @if(session('feedback_submitted'))
                    <span class="px-4 py-2 bg-emerald-50 text-emerald-700 text-xs font-semibold rounded-lg border border-emerald-200">
                        {{ session('feedback_submitted') }}
                    </span>
                @else
                    <div class="flex items-center gap-3">
                        <form action="{{ route('odden.help.vote', $article->slug) }}" method="POST">
                            @csrf
                            <input type="hidden" name="type" value="helpful">
                            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white hover:bg-slate-100 border border-slate-200 rounded-lg text-xs font-semibold text-slate-700 shadow-sm transition">
                                👍 Yes
                            </button>
                        </form>
                        <form action="{{ route('odden.help.vote', $article->slug) }}" method="POST">
                            @csrf
                            <input type="hidden" name="type" value="not_helpful">
                            <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white hover:bg-slate-100 border border-slate-200 rounded-lg text-xs font-semibold text-slate-700 shadow-sm transition">
                                👎 No
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </article>

        <!-- Related Articles -->
        @if($relatedArticles->count() > 0)
            <div class="mt-10">
                <h3 class="text-base font-bold text-slate-900 mb-4">Related Guides in {{ $article->category }}</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach($relatedArticles as $rel)
                        <a href="{{ route('odden.help.show', $rel->slug) }}" class="bg-white p-4 rounded-xl border border-slate-200 hover:border-indigo-300 transition block">
                            <h4 class="text-sm font-bold text-slate-800 hover:text-indigo-600 transition mb-1">{{ $rel->title }}</h4>
                            <span class="text-xs text-slate-400">{{ $rel->views_count }} views</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </main>

    <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-400 mt-12">
        &copy; {{ date('Y') }} Odden CRM Inc. All rights reserved.
    </footer>
</body>
</html>
