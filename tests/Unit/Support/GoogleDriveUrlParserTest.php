<?php

use App\Support\GoogleDriveUrlParser;

it('extracts file id from /file/d/ URL with view suffix', function (): void {
    expect(GoogleDriveUrlParser::extractFileId('https://drive.google.com/file/d/1aBcD_ef-Ghij123K/view?usp=sharing'))
        ->toBe('1aBcD_ef-Ghij123K');
});

it('extracts file id from /file/d/ URL without query string', function (): void {
    expect(GoogleDriveUrlParser::extractFileId('https://drive.google.com/file/d/1aBcD_ef-Ghij123K/view'))
        ->toBe('1aBcD_ef-Ghij123K');
});

it('extracts file id from /file/d/ URL with preview suffix', function (): void {
    expect(GoogleDriveUrlParser::extractFileId('https://drive.google.com/file/d/1aBcD_ef-Ghij123K/preview'))
        ->toBe('1aBcD_ef-Ghij123K');
});

it('extracts file id from open?id= URL', function (): void {
    expect(GoogleDriveUrlParser::extractFileId('https://drive.google.com/open?id=1aBcD_ef-Ghij123K'))
        ->toBe('1aBcD_ef-Ghij123K');
});

it('extracts file id from uc?id= URL', function (): void {
    expect(GoogleDriveUrlParser::extractFileId('https://drive.google.com/uc?id=1aBcD_ef-Ghij123K&export=download'))
        ->toBe('1aBcD_ef-Ghij123K');
});

it('extracts file id from docs.google.com URL', function (): void {
    expect(GoogleDriveUrlParser::extractFileId('https://docs.google.com/file/d/1aBcD_ef-Ghij123K/edit'))
        ->toBe('1aBcD_ef-Ghij123K');
});

it('handles whitespace around URL', function (): void {
    expect(GoogleDriveUrlParser::extractFileId('  https://drive.google.com/file/d/1aBcD_ef-Ghij123K/view  '))
        ->toBe('1aBcD_ef-Ghij123K');
});

it('returns null for empty string', function (): void {
    expect(GoogleDriveUrlParser::extractFileId(''))->toBeNull();
});

it('rejects non-drive hosts even with /d/ path', function (): void {
    expect(GoogleDriveUrlParser::extractFileId('https://example.com/file/d/1aBcD_ef-Ghij123K/view'))
        ->toBeNull();
});

it('rejects phishing-like google subdomain URLs', function (): void {
    expect(GoogleDriveUrlParser::extractFileId('https://drive.google.com.evil.com/file/d/1aBcD_ef-Ghij123K/view'))
        ->toBeNull();
});

it('accepts proper drive.google.com subdomains', function (): void {
    // e.g. drive.google.com or any .drive.google.com subdomain
    expect(GoogleDriveUrlParser::extractFileId('https://drive.google.com/file/d/1aBcD_ef-Ghij123K/view'))
        ->toBe('1aBcD_ef-Ghij123K');
});

it('returns null for YouTube URL', function (): void {
    expect(GoogleDriveUrlParser::extractFileId('https://www.youtube.com/watch?v=dQw4w9WgXcQ'))
        ->toBeNull();
});

it('returns null for URL without file id', function (): void {
    expect(GoogleDriveUrlParser::extractFileId('https://drive.google.com/drive/my-drive'))
        ->toBeNull();
});

it('rejects too-short file ids', function (): void {
    expect(GoogleDriveUrlParser::extractFileId('https://drive.google.com/file/d/abc/view'))
        ->toBeNull();
});
