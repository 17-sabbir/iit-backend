<?php

return [
    'roles' => [
        'Student' => ['books.read', 'borrow.request', 'reservation.manage', 'profile.manage'],
        'Teacher' => ['books.read', 'borrow.request', 'reservation.manage', 'profile.manage'],
        'Librarian' => ['books.*', 'borrow.*', 'reservation.*', 'course.*', 'payment.*', 'report.*', 'settings.manage', 'profile.manage'],
        'Director' => ['books.*', 'borrow.*', 'reservation.*', 'course.*', 'payment.*', 'report.*', 'settings.manage', 'profile.manage'],
    ],
];