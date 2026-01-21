#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <libgen.h>
#include <unistd.h>
#include <limits.h>
#include <curl/curl.h>

#ifdef __APPLE__
#include <mach-o/dyld.h>
#endif

#define MAX_BUF 1024
#define MAX_MSG_SIZE 4096

void get_exe_dir(char *buffer, size_t size) {
    char path[PATH_MAX];
    uint32_t size32 = (uint32_t)sizeof(path);

    #ifdef __APPLE__
    if (_NSGetExecutablePath(path, &size32) == 0) {
        char *real_path = realpath(path, NULL);
        if (real_path) {
            strncpy(buffer, dirname(real_path), size);
            free(real_path);
        }
    }
    #else
    if (readlink("/proc/self/exe", path, size) != -1) {
        strncpy(buffer, dirname(path), size);
    }
    #endif
}

int get_config_value(const char *config_path, const char *key, char *value, size_t val_size) {
    FILE *fp = fopen(config_path, "r");
    if (!fp) return 0;

    char line[MAX_BUF];
    int found = 0;

    while (fgets(line, sizeof(line), fp)) {
        line[strcspn(line, "\n")] = 0;
        char *eq_pos = strchr(line, '=');
        if (eq_pos) {
            *eq_pos = 0; 
            if (strcmp(line, key) == 0) {
                strncpy(value, eq_pos + 1, val_size);
                found = 1;
                break;
            }
        }
    }

    fclose(fp);
    return found;
}

int main(int argc, char *argv[]) {
    char chat_id[MAX_BUF] = {0};
    char message[MAX_MSG_SIZE] = {0};

    char exe_dir[PATH_MAX];
    get_exe_dir(exe_dir, sizeof(exe_dir));
    char config_path[PATH_MAX];
    snprintf(config_path, sizeof(config_path), "%s/telegram.conf", exe_dir);

    // Логика аргументов
    if (argc >= 2) {
        // Если передан аргумент - считаем его сообщением (старый режим)
        strncpy(message, argv[1], sizeof(message) - 1);
        
        if (!get_config_value(config_path, "CHAT_ID", chat_id, sizeof(chat_id))) {
             fprintf(stderr, "Ошибка: CHAT_ID не найден в конфиге.\n");
             return 1;
        }
    } else {
        // Если аргументов нет - читаем из stdin (новый режим)
        // Читаем ID из конфига
        if (!get_config_value(config_path, "CHAT_ID", chat_id, sizeof(chat_id))) {
             fprintf(stderr, "Ошибка: CHAT_ID не найден в конфиге.\n");
             return 1;
        }

        // Читаем stdin
        size_t bytes_read = fread(message, 1, sizeof(message) - 1, stdin);
        if (bytes_read == 0) {
            fprintf(stderr, "Ошибка: пустое сообщение.\n");
            return 1;
        }
    }

    char bot_token[MAX_BUF];
    if (!get_config_value(config_path, "BOT_TOKEN", bot_token, sizeof(bot_token))) {
        fprintf(stderr, "Ошибка: BOT_TOKEN не найден в %s\n", config_path);
        return 1;
    }

    char bot_name[MAX_BUF] = "Bot"; 
    get_config_value(config_path, "BOT_NAME", bot_name, sizeof(bot_name));

    CURL *curl;
    CURLcode res;

    curl_global_init(CURL_GLOBAL_ALL);
    curl = curl_easy_init();

    if(curl) {
        char *encoded_text = curl_easy_escape(curl, message, 0);
        if (!encoded_text) {
            fprintf(stderr, "Ошибка: сбой кодирования текста.\n");
            return 1;
        }

        // Выделяем память под URL (с запасом)
        size_t url_len = strlen(bot_token) + strlen(chat_id) + strlen(encoded_text) + 200;
        char *url = malloc(url_len);
        
        if (!url) {
            fprintf(stderr, "Ошибка памяти.\n");
            curl_free(encoded_text);
            return 1;
        }

        snprintf(url, url_len, "https://api.telegram.org/bot%s/sendMessage?chat_id=%s&parse_mode=HTML&text=%s", bot_token, chat_id, encoded_text);

        curl_easy_setopt(curl, CURLOPT_URL, url);
        curl_easy_setopt(curl, CURLOPT_FOLLOWLOCATION, 1L);

        res = curl_easy_perform(curl);

        if(res != CURLE_OK) {
            fprintf(stderr, "Ошибка curl: %s\n", curl_easy_strerror(res));
        } else {
            printf("Сообщение отправлено (%s).\n", bot_name);
        }

        free(url);
        curl_free(encoded_text);
        curl_easy_cleanup(curl);
    }

    curl_global_cleanup();
    return 0;
}
