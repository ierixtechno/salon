@include('errors.layout', [
    'code' => 409,
    'title' => 'That can\'t be done right now',
    'message' => $exception->getMessage() ?: 'This action conflicts with the current state of the record.',
    'retry' => false,
    'showReference' => false,
])
