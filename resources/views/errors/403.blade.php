@include('errors.layout', [
    'code' => 403,
    'title' => 'Not allowed',
    'message' => $exception->getMessage() ?: 'You don\'t have permission to open this page.',
    'retry' => false,
    'showReference' => false,
])
