<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * The terminal channel (`terminal.{terminalPublicId}`) is deliberately
 * PUBLIC, not private: the Customer Display is an unattended screen with no
 * login (per docs/architecture/05-screen-wireframes.md §5.2 — "no input"),
 * so it has no session to authorize a private channel with. The write path
 * stays fully authenticated — only the Cashier POS's server-side requests
 * ever call broadcast(); the channel itself is a read-only feed keyed by the
 * terminal's ULID public_id, which is long and effectively unguessable.
 * No Broadcast::channel() entry is needed here for public channels.
 */
