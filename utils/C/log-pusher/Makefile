CC = gcc
CFLAGS = -Wall -O2
# Поддержка путей для Intel (/usr/local) и Apple Silicon (/opt/homebrew)
INCLUDES = -I/usr/local/include -I/usr/local/opt/libpq/include -I/opt/homebrew/include -I/opt/homebrew/opt/libpq/include
LDFLAGS = -L/usr/local/lib -L/usr/local/opt/libpq/lib -L/opt/homebrew/lib -L/opt/homebrew/opt/libpq/lib -lpq -ljson-c

TARGET = log-pusher
SRC = log_pusher.c

all: $(TARGET)

$(TARGET): $(SRC)
	$(CC) $(CFLAGS) $(INCLUDES) -o $(TARGET) $(SRC) $(LDFLAGS)

clean:
	rm -f $(TARGET)

.PHONY: all clean
