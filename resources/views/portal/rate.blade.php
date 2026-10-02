<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Your Support Experience - Ticket #{{ $ticket->ticket_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex flex-col items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-3xl border border-slate-200 p-8 sm:p-10 shadow-lg text-center">
        <div class="w-16 h-16 bg-amber-50 text-amber-500 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-4 shadow-sm">
            ★
        </div>

        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight mb-2">How was our support?</h1>
        <p class="text-sm text-slate-500 mb-6">
            Ticket <span class="font-mono font-semibold text-slate-700">#{{ $ticket->ticket_number }}</span> was recently marked resolved. Please rate your overall satisfaction with our team.
        </p>

        @if ($errors->any())
            <div class="mb-4 p-3 rounded-xl bg-rose-50 text-rose-700 text-xs">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('odden.support.submitRating', $ticket->portal_token) }}" method="POST" class="space-y-6">
            @csrf

            <!-- 1 to 5 Star Rating Radio Buttons -->
            <div class="flex items-center justify-center gap-2">
                @for ($star = 1; $star <= 5; $star++)
                    <label class="cursor-pointer group">
                        <input type="radio" name="rating" value="{{ $star }}" required class="sr-only peer" {{ old('rating', $ticket->csat_rating) == $star ? 'checked' : '' }}>
                        <div class="w-12 h-12 rounded-xl flex flex-col items-center justify-center border border-slate-200 peer-checked:bg-amber-500 peer-checked:border-amber-500 peer-checked:text-white hover:border-amber-400 transition text-slate-400">
                            <span class="text-xl">★</span>
                            <span class="text-[10px] font-bold">{{ $star }}</span>
                        </div>
                    </label>
                @endfor
            </div>

            <div>
                <label for="comment" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 text-left">Additional Feedback (Optional)</label>
                <textarea name="comment" id="comment" rows="3" placeholder="Tell us what we did well or how we can improve..."
                          class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none text-sm transition">{{ old('comment', $ticket->csat_comment) }}</textarea>
            </div>

            <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl text-sm transition shadow-sm">
                Submit Feedback
            </button>
        </form>

        <div class="mt-6">
            <a href="{{ route('odden.support.show', $ticket->portal_token) }}" class="text-xs font-semibold text-slate-400 hover:text-slate-600 transition">
                &larr; Return to Ticket #{{ $ticket->ticket_number }}
            </a>
        </div>
    </div>

</body>
</html>
