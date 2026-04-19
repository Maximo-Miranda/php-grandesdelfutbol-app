<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Team Standings
    |--------------------------------------------------------------------------
    | Enables the Posiciones section with team standings, Season and Team
    | management, and the team combobox + friendly/single-team toggles in
    | the match form. When off, matches keep using free-text team names and
    | the Posiciones menu item is hidden.
    */
    'team_standings' => (bool) env('FEATURE_TEAM_STANDINGS', true),
];
