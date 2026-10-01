<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center & Knowledge Base - Focal</title>
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
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('focal.help.index') }}" class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold text-lg shadow-sm">F</span>
                    <span class="font-bold text-lg text-slate-900 tracking-tight">Focal Help Center</span>
                </a>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('focal.support.create') }}" class="inline-flex items-center justify-center px-4 py-2 text-sm font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 transition shadow-sm">
                    Submit a Ticket
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Search Section -->
    <section class="bg-gradient-to-b from-indigo-900 via-indigo-800 to-indigo-950 text-white py-14 px-4 sm:px-6 text-center">
        <div class="max-w-3xl mx-auto">
            <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight mb-4">How can we help you today?</h1>
            <p class="text-indigo-200 text-base sm:text-lg mb-8 max-w-xl mx-auto">Search our knowledge base for guides, API specifications, and troubleshooting steps.</p>
            
            <form action="{{ route('focal.help.index') }}" method="GET" class="relative max-w-2xl mx-auto shadow-2xl rounded-xl">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" name="q" value="{{ $search }}" placeholder="Search articles by keyword (e.g. SAML, Invoices, Webhooks)..." 
                       class="w-full pl-11 pr-28 py-4 rounded-xl text-slate-900 placeholder-slate-400 bg-white border-0 focus:ring-4 focus:ring-indigo-400 outline-none text-base font-medium">
                <button type="submit" class="absolute right-2 top-2 bottom-2 px-5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-lg text-sm transition">
                    Search
                </button>
            </form>
        </div>
    </section>

    <!-- Main Content -->
    <main class="flex-1 max-w-6xl mx-auto w-full px-4 sm:px-6 py-10">
        
        <!-- Category Filter Pills -->
        @if(!empty($categories))
            <div class="flex items-center gap-2 overflow-x-auto pb-4 mb-8 text-sm">
                <a href="{{ route('focal.help.index', ['q' => $search]) }}" 
                   class="px-3.5 py-1.5 rounded-full font-medium transition {{ empty($selectedCategory) ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100' }}">
                    All Categories
                </a>
                @foreach($categories as $cat)
                    <a href="{{ route('focal.help.index', ['category' => $cat, 'q' => $search]) }}" 
                       class="px-3.5 py-1.5 rounded-full font-medium whitespace-nowrap transition {{ $selectedCategory === $cat ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-100' }}">
                        {{ $cat }}
                    </a>
                @endforeach
            </div>
        @endif

        @if(!empty($search))
            <div class="mb-6 flex items-center justify-between">
                <p class="text-sm text-slate-500">Showing search results for "<span class="font-semibold text-slate-800">{{ $search }}</span>"</p>
                <a href="{{ route('focal.help.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700">Clear Search</a>
            </div>
        @endif

        <!-- Articles Grid -->
        @if($articles->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($articles as $article)
                    <a href="{{ route('focal.help.show', $article->slug) }}" class="group bg-white p-6 rounded-2xl border border-slate-200 hover:border-indigo-300 hover:shadow-md transition flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700">{{ $article->category }}</span>
                                <span class="text-xs text-slate-400">{{ $article->views_count }} views</span>
                            </div>
                            <h2 class="text-lg font-bold text-slate-900 group-hover:text-indigo-600 transition mb-2 line-clamp-2">
                                {{ $article->title }}
                            </h2>
                            <p class="text-sm text-slate-500 line-clamp-3">
                                {{ Str::limit(strip_tags($article->body), 130) }}
                            </p>
                        </div>
                        <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400">
                            <span>👍 {{ $article->helpful_count }} helpful</span>
                            <span class="font-semibold text-indigo-600 group-hover:translate-x-0.5 transition inline-flex items-center">
                                Read Guide &rarr;
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $articles->links() }}
            </div>
        @else
            <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center max-w-lg mx-auto">
                <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-1">No articles found</h3>
                <p class="text-sm text-slate-500 mb-6">We couldn't find any guides matching your criteria.</p>
                <a href="{{ route('focal.support.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-semibold transition">
                    Contact Support Team
                </a>
            </div>
        @endif

        <!-- Help Banner -->
        <div class="mt-16 bg-white border border-slate-200 rounded-2xl p-8 flex flex-col md:flex-row items-center justify-between gap-6 shadow-sm">
            <div>
                <h3 class="text-xl font-bold text-slate-900 mb-1">Still need assistance?</h3>
                <p class="text-sm text-slate-500">Our customer support specialists are on standby to answer your questions.</p>
            </div>
            <a href="{{ route('focal.support.create') }}" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl text-sm transition shadow-sm shrink-0">
                Open a Support Ticket
            </a>
        </div>
    </main>

    <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-400">
        &copy; {{ date('Y') }} Focal CRM Inc. All rights reserved.
    </footer>
</body>
</html>
