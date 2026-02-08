#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <libpq-fe.h>
#include <json-c/json.h>

#define CONFIG_PATH "utils/C/log-pusher/db.conf"

const char* get_log_path() {
    static const char* paths[] = {
        "storage/logs/local.log", "storage/logs/laravel.log",
        "../storage/logs/local.log", "../storage/logs/laravel.log",
        "../../../storage/logs/local.log", "../../../storage/logs/laravel.log"
    };
    for (int i = 0; i < 6; i++) {
        if (access(paths[i], R_OK) == 0) return paths[i];
    }
    return NULL;
}

int get_config_value(const char *key, char *value, size_t val_size) {
    const char* config_paths[] = {CONFIG_PATH, "db.conf", "../utils/C/log-pusher/db.conf"};
    FILE *fp = NULL;
    for (int i = 0; i < 3; i++) {
        fp = fopen(config_paths[i], "r");
        if (fp) break;
    }
    if (!fp) return 0;
    char line[256];
    while (fgets(line, sizeof(line), fp)) {
        char *k = strtok(line, "=");
        char *v = strtok(NULL, "\n");
        if (k && v && strcmp(k, key) == 0) {
            strncpy(value, v, val_size - 1);
            fclose(fp);
            return 1;
        }
    }
    fclose(fp);
    return 0;
}

// Запись обычного запроса
void push_request_to_db(PGconn *conn, const char *timestamp, struct json_object *data) {
    struct json_object *obj_method, *obj_url, *obj_ip, *obj_ua, *obj_status, *obj_exec;
    json_object_object_get_ex(data, "method", &obj_method);
    json_object_object_get_ex(data, "url", &obj_url);
    json_object_object_get_ex(data, "ip", &obj_ip);
    json_object_object_get_ex(data, "user_agent", &obj_ua);
    json_object_object_get_ex(data, "status", &obj_status);
    json_object_object_get_ex(data, "execution_time_ms", &obj_exec);

    char status_str[10], exec_str[10];
    sprintf(status_str, "%d", json_object_get_int(obj_status));
    sprintf(exec_str, "%d", json_object_get_int(obj_exec));

    const char *paramValues[] = {
        json_object_get_string(obj_method),
        json_object_get_string(obj_url),
        json_object_get_string(obj_ip),
        json_object_get_string(obj_ua) ? json_object_get_string(obj_ua) : "",
        "{}", "{}", status_str, exec_str, timestamp
    };

    const char *query = "INSERT INTO request_logs (method, url, ip_address, user_agent, request_headers, request_body, response_status, execution_time, created_at, updated_at) "
                        "VALUES ($1, $2, $3, $4, $5::jsonb, $6::jsonb, $7, $8, $9, NOW())";
    PGresult *res = PQexecParams(conn, query, 9, NULL, paramValues, NULL, NULL, 0);
    if (PQresultStatus(res) != PGRES_COMMAND_OK) fprintf(stderr, "Request INSERT failed: %s\n", PQerrorMessage(conn));
    PQclear(res);
}

// Запись ошибки
void push_error_to_db(PGconn *conn, const char *timestamp, const char *level, const char *message, const char *context_json) {
    const char *paramValues[] = { level, message, context_json ? context_json : "{}", timestamp };
    const char *query = "INSERT INTO error_logs (level, message, context, created_at) VALUES ($1, $2, $3::jsonb, $4)";
    PGresult *res = PQexecParams(conn, query, 4, NULL, paramValues, NULL, NULL, 0);
    if (PQresultStatus(res) != PGRES_COMMAND_OK) fprintf(stderr, "Error INSERT failed: %s\n", PQerrorMessage(conn));
    PQclear(res);
}

void process_line(PGconn *conn, char *line) {
    char timestamp[20];
    if (line[0] != '[' || strlen(line) < 20) return;
    strncpy(timestamp, line + 1, 19);
    timestamp[19] = '\0';

    // 1. Проверка на Request handled (INFO)
    if (strstr(line, "Request handled")) {
        char *json_start = strchr(line, '{');
        if (json_start) {
            struct json_object *parsed_json = json_tokener_parse(json_start);
            if (parsed_json) {
                push_request_to_db(conn, timestamp, parsed_json);
                json_object_put(parsed_json);
            }
        }
        return;
    }

    // 2. Проверка на ошибки (ERROR, CRITICAL, EMERGENCY, ALERT)
    const char *levels[] = {"ERROR", "CRITICAL", "EMERGENCY", "ALERT", "WARNING"};
    for (int i = 0; i < 5; i++) {
        char pattern[20];
        sprintf(pattern, "local.%s:", levels[i]);
        char *level_pos = strstr(line, pattern);
        if (level_pos) {
            char *msg_start = level_pos + strlen(pattern) + 1;
            char *json_start = strchr(msg_start, '{');
            char message[4096] = {0};
            
            if (json_start) {
                strncpy(message, msg_start, json_start - msg_start);
                push_error_to_db(conn, timestamp, levels[i], message, json_start);
            } else {
                push_error_to_db(conn, timestamp, levels[i], msg_start, "{}");
            }
            return;
        }
    }
}

int main(int argc, char *argv[]) {
    int once_mode = (argc > 1 && strcmp(argv[1], "--once") == 0);
    if (once_mode) {
        char cmd[256];
        snprintf(cmd, sizeof(cmd), "pgrep -f %s | grep -v %d | xargs kill -9 2>/dev/null", argv[0], getpid());
        system(cmd);
        sleep(1);
    }
    char conn_info[512];
    if (!get_config_value("CONN_INFO", conn_info, sizeof(conn_info))) return 1;
    PGconn *conn = PQconnectdb(conn_info);
    if (PQstatus(conn) != CONNECTION_OK) { PQfinish(conn); return 1; }
    const char* log_path = get_log_path();
    if (!log_path) { PQfinish(conn); return 1; }
    FILE *fp = fopen(log_path, "r");
    if (!fp) { PQfinish(conn); return 1; }

    printf("Pusher started. Log: %s. Mode: %s\n", log_path, once_mode ? "ONCE" : "DAEMON");
    fseek(fp, 0, once_mode ? SEEK_SET : SEEK_END);

    char line[16384]; // Увеличил буфер для длинных ошибок
    while (1) {
        if (fgets(line, sizeof(line), fp)) {
            process_line(conn, line);
        } else {
            if (once_mode) break;
            usleep(200000); 
            clearerr(fp);
        }
    }
    PQfinish(conn);
    fclose(fp);
    return 0;
}