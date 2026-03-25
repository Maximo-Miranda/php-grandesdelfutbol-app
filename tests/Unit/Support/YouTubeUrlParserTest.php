<?php

use App\Support\YouTubeUrlParser;

it('extracts video id from standard youtube url', function (): void {
    expect(YouTubeUrlParser::extractVideoId('https://www.youtube.com/watch?v=dQw4w9WgXcQ'))
        ->toBe('dQw4w9WgXcQ');
});

it('extracts video id from youtube url without www', function (): void {
    expect(YouTubeUrlParser::extractVideoId('https://youtube.com/watch?v=dQw4w9WgXcQ'))
        ->toBe('dQw4w9WgXcQ');
});

it('extracts video id from short youtube url', function (): void {
    expect(YouTubeUrlParser::extractVideoId('https://youtu.be/dQw4w9WgXcQ'))
        ->toBe('dQw4w9WgXcQ');
});

it('extracts video id from embed url', function (): void {
    expect(YouTubeUrlParser::extractVideoId('https://www.youtube.com/embed/dQw4w9WgXcQ'))
        ->toBe('dQw4w9WgXcQ');
});

it('extracts raw 11-character video id', function (): void {
    expect(YouTubeUrlParser::extractVideoId('dQw4w9WgXcQ'))
        ->toBe('dQw4w9WgXcQ');
});

it('handles video id with hyphens and underscores', function (): void {
    expect(YouTubeUrlParser::extractVideoId('abc-_def123'))
        ->toBe('abc-_def123');
});

it('extracts video id from url with extra query params', function (): void {
    expect(YouTubeUrlParser::extractVideoId('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=120'))
        ->toBe('dQw4w9WgXcQ');
});

it('returns null for empty string', function (): void {
    expect(YouTubeUrlParser::extractVideoId(''))->toBeNull();
});

it('returns null for invalid url', function (): void {
    expect(YouTubeUrlParser::extractVideoId('https://example.com/video'))->toBeNull();
});

it('returns null for string that is not 11 characters', function (): void {
    expect(YouTubeUrlParser::extractVideoId('short'))->toBeNull();
});
