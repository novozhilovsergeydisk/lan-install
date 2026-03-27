#define _GNU_SOURCE
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <time.h>

#define MAX_LINE    4096
#define LOG_PATH    "/var/www/lan-install/storage/logs/local.log"
#define TEST_BOT_TOKEN "8549290522:AAFow8QDhmsn6UnEMCaHYd5Zvfys4H9dPYo"
#define TEST_CHAT_ID   "1020570278"

void send_telegram_notification(const char *error_text) {
    char cmd[MAX_LINE + 1024];
    char escaped_text[MAX_LINE];
    
    int j = 0;
    for (int i = 0; error_text[i] != '\0' && j < MAX_LINE - 10; i++) {
        if (error_text[i] == '\'' || error_text[i] == '\"' || error_text[i] == '`' || error_text[i] == '$') escaped_text[j++] = ' ';
        else if (error_text[i] == '\n' || error_text[i] == '\r') escaped_text[j++] = ' ';
        else escaped_text[j++] = error_text[i];
    }
    escaped_text[j] = '\0';

    snprintf(cmd, sizeof(cmd), 
             "echo '⚠️ <b>Ошибка в local.log:</b>\n\n%s' | ssh vpn-server \"/var/www/lan-install/utils/C/notify-bot/telegram_notify -t %s -c %s\" > /dev/null 2>&1", 
             escaped_text, TEST_BOT_TOKEN, TEST_CHAT_ID);
    
    printf("[%ld] Найдена ошибка, отправляем в Telegram...\n", (long)time(NULL));
    fflush(stdout);
    system(cmd);
}

int main() {
    char line[MAX_LINE];
    char tail_cmd[512];
    
    printf("--- Мониторинг логов v3.0 (tail-based) ---\n");
    printf("Файл: %s\n", LOG_PATH);
    fflush(stdout);

    // Используем tail -F, который умеет следить за пересоздаваемыми файлами
    snprintf(tail_cmd, sizeof(tail_cmd), "tail -n 0 -F %s", LOG_PATH);
    
    FILE *pipe = popen(tail_cmd, "r");
    if (!pipe) {
        perror("popen failed");
        return 1;
    }

    while (fgets(line, sizeof(line), pipe)) {
        if (strcasestr(line, "ERROR") || strcasestr(line, "Exception") || 
            strcasestr(line, "CRITICAL") || strcasestr(line, "Stack trace")) {
            send_telegram_notification(line);
        }
    }

    pclose(pipe);
    return 0;
}
