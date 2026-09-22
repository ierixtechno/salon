@include('errors.layout', [
    'code' => 500,
    'title' => 'Something went wrong on our side',
    'message' => 'We\'ve logged the problem and will look into it. Please try again in a moment.',
    'retry' => true,
    'showReference' => true,
])
