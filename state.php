<?php
/**
 * state.php
 * نگهداری ساده وضعیت هر چت (آخرین متن تولیدشده، ریپوی انتخابی) در یک فایل JSON.
 * برای استفاده سبک کافیه؛ نیازی به دیتابیس نیست.
 */

function state_path(int $chatId): string
{
    return STATE_DIR . '/chat_' . $chatId . '.json';
}

function state_get(int $chatId): array
{
    $path = state_path($chatId);
    if (!file_exists($path)) {
        return [];
    }
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function state_set(int $chatId, array $data): void
{
    file_put_contents(state_path($chatId), json_encode($data, JSON_UNESCAPED_UNICODE));
}

function state_update(int $chatId, array $partial): array
{
    $state = array_merge(state_get($chatId), $partial);
    state_set($chatId, $state);
    return $state;
}
