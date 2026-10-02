<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit a Support Ticket - Odden</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex flex-col">

    <header class="bg-white border-b border-slate-200">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <a href="{{ route('odden.help.index') }}" class="flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold text-lg shadow-sm">F</span>
                <span class="font-bold text-lg text-slate-900 tracking-tight">Odden Support</span>
            </a>
            <a href="{{ route('odden.help.index') }}" class="text-sm font-semibold text-slate-600 hover:text-indigo-600 transition">
                &larr; Knowledge Base
            </a>
        </div>
    </header>

    <main class="flex-1 max-w-2xl mx-auto w-full px-4 sm:px-6 py-10">
        <div class="bg-white rounded-2xl border border-slate-200 p-8 sm:p-10 shadow-sm">
            <div class="mb-8">
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight mb-2">Submit a Support Ticket</h1>
                <p class="text-sm text-slate-500">Provide details on the issue you are experiencing and our support team will reply promptly.</p>
            </div>

            @if ($errors->any())
                <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('odden.support.store') }}" method="POST" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Your Full Name</label>
                        <input type="text" name="name" id="name" required value="{{ old('name') }}" placeholder="e.g. John Connor"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm transition">
                    </div>
                    <div>
                        <label for="email" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Work Email</label>
                        <input type="email" name="email" id="email" required value="{{ old('email') }}" placeholder="john@company.com"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm transition">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div class="sm:col-span-2">
                        <label for="subject" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Subject / Issue Summary</label>
                        <input type="text" name="subject" id="subject" required value="{{ old('subject') }}" placeholder="Brief summary of the issue"
                               class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm transition">
                    </div>
                    <div>
                        <label for="priority" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Urgency / Priority</label>
                        <select name="priority" id="priority" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm bg-white transition">
                            <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>Low (General)</option>
                            <option value="medium" {{ old('priority', 'medium') === 'medium' ? 'selected' : '' }}>Medium (Standard)</option>
                            <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>High (Degraded Service)</option>
                            <option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>Urgent (Outage / Blocking)</option>
                        </select>
                    </div>
                </div>

                <!-- Live Self-Service Deflection Widget -->
                <div id="deflection-container" class="hidden rounded-xl border border-indigo-100 bg-indigo-50/50 p-4 transition-all">
                    <div class="flex items-center gap-2 mb-3 text-indigo-900 font-semibold text-xs uppercase tracking-wider">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Suggested Instant Answers
                    </div>
                    <div id="deflection-results" class="space-y-3"></div>
                </div>

                <!-- Resolved Banner -->
                <div id="deflected-banner" class="hidden rounded-xl bg-emerald-50 border border-emerald-200 p-5 text-emerald-800 text-sm">
                    <div class="flex items-start gap-3">
                        <span class="text-xl">🎉</span>
                        <div>
                            <p class="font-bold text-base mb-1">Awesome! Glad we could help resolve your issue!</p>
                            <p class="text-emerald-700 text-xs">Your inquiry was solved self-service. If you ever need anything else, you can return to our <a href="{{ route('odden.help.index') }}" class="underline font-semibold">Help Center</a>.</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="description" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Problem Description & Steps to Reproduce</label>
                    <textarea name="description" id="description" rows="6" required placeholder="Please describe what happened, any error messages displayed, and steps we can take to reproduce the issue..."
                              class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm transition">{{ old('description') }}</textarea>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-xs text-slate-400">Target response SLA applied automatically.</span>
                    <button type="submit" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl text-sm transition shadow-sm">
                        Submit Ticket
                    </button>
                </div>
            </form>
        </div>
    </main>

    <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-400 mt-12">
        &copy; {{ date('Y') }} Odden CRM Inc. All rights reserved.
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const subjectInput = document.getElementById('subject');
            const container = document.getElementById('deflection-container');
            const resultsDiv = document.getElementById('deflection-results');
            const deflectedBanner = document.getElementById('deflected-banner');
            const ticketForm = document.querySelector('form');
            let timeout = null;

            const el = (tag, className, text) => {
                const node = document.createElement(tag);
                node.className = className;
                if (text !== undefined) {
                    node.textContent = String(text ?? '');
                }
                return node;
            };

            const safeUrl = (url) => {
                try {
                    const parsed = new URL(String(url), window.location.href);
                    return ['http:', 'https:'].includes(parsed.protocol) ? parsed.href : '#';
                } catch (e) {
                    return '#';
                }
            };

            const renderSuggestion = (item) => {
                const card = el('div', 'bg-white rounded-lg p-3 border border-indigo-100/80 shadow-xs flex items-start justify-between gap-4');
                const info = el('div', 'space-y-1');
                const heading = el('div', 'flex items-center gap-2');

                const category = el('span', 'px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600', item.category);
                const link = el('a', 'text-sm font-semibold text-indigo-600 hover:text-indigo-800 underline', `${item.title ?? ''} \u2192`);
                link.href = safeUrl(item.url);
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                heading.append(category, link);

                info.append(heading, el('p', 'text-xs text-slate-500 line-clamp-2', item.excerpt));

                const button = el('button', 'shrink-0 px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-semibold rounded-lg border border-emerald-200 transition', '\u2713 This solved it');
                button.type = 'button';
                button.addEventListener('click', () => window.recordDeflection(item.id));

                card.append(info, button);

                return card;
            };

            subjectInput.addEventListener('input', (e) => {
                const query = e.target.value.trim();
                clearTimeout(timeout);
                if (query.length < 3) {
                    container.classList.add('hidden');
                    resultsDiv.replaceChildren();
                    return;
                }

                timeout = setTimeout(() => {
                    fetch(`{{ route('odden.service.knowledge.suggest') }}?q=${encodeURIComponent(query)}`)
                        .then(r => r.json())
                        .then(payload => {
                            if (!payload.data || payload.data.length === 0) {
                                container.classList.add('hidden');
                                resultsDiv.replaceChildren();
                                return;
                            }

                            // Build nodes with textContent so article titles, categories and excerpts are never parsed as HTML.
                            resultsDiv.replaceChildren(...payload.data.map(renderSuggestion));
                            container.classList.remove('hidden');
                        })
                        .catch(() => {});
                }, 300);
            });

            window.recordDeflection = (articleId) => {
                fetch(`{{ route('odden.service.knowledge.deflect') }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ article_id: articleId })
                }).then(() => {
                    container.classList.add('hidden');
                    deflectedBanner.classList.remove('hidden');
                    ticketForm.classList.add('opacity-50', 'pointer-events-none');
                });
            };
        });
    </script>
</body>
</html>
