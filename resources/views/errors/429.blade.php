@include('errors.layout', [
    'code' => 429,
    'title' => 'Too many attempts',
    'message' => 'You\'ve made too many requests in a short time. Please wait a minute and try again.',
    'retry' => true,
    'showReference' => false,
])
