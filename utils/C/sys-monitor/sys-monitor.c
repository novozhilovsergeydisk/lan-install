#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#ifdef __APPLE__
#include <sys/types.h>
#include <sys/sysctl.h>
#include <mach/mach.h>
#include <mach/vm_statistics.h>
#include <mach/mach_types.h>
#include <mach/mach_init.h>
#include <mach/mach_host.h>
#include <sys/statvfs.h>
#elif __linux__
#include <sys/sysinfo.h>
#include <sys/statvfs.h>
#include <unistd.h>
#endif

void get_cpu_load(double *load1, double *load5, double *load15) {
    double loadavg[3];
    if (getloadavg(loadavg, 3) != -1) {
        *load1 = loadavg[0];
        *load5 = loadavg[1];
        *load15 = loadavg[2];
    } else {
        *load1 = *load5 = *load15 = -1.0;
    }
}

int get_cpu_cores() {
    int cores = 1;
#ifdef __APPLE__
    size_t len = sizeof(cores);
    sysctlbyname("hw.logicalcpu", &cores, &len, NULL, 0);
#elif __linux__
    cores = sysconf(_SC_NPROCESSORS_ONLN);
#endif
    return cores;
}

void get_memory_stats(unsigned long long *total, unsigned long long *used, unsigned long long *free, unsigned long long *available) {
#ifdef __APPLE__
    int mib[2];
    int64_t physical_memory;
    size_t length;
    
    mib[0] = CTL_HW;
    mib[1] = HW_MEMSIZE;
    length = sizeof(int64_t);
    sysctl(mib, 2, &physical_memory, &length, NULL, 0);
    *total = physical_memory;

    vm_size_t page_size;
    mach_port_t mach_port;
    mach_msg_type_number_t count;
    vm_statistics64_data_t vm_stats;

    mach_port = mach_host_self();
    count = sizeof(vm_stats) / sizeof(natural_t);
    if (KERN_SUCCESS == host_page_size(mach_port, &page_size) &&
        KERN_SUCCESS == host_statistics64(mach_port, HOST_VM_INFO,
                                        (host_info64_t)&vm_stats, &count)) {
        
        unsigned long long free_mem = (unsigned long long)vm_stats.free_count * page_size;
        unsigned long long inactive_mem = (unsigned long long)vm_stats.inactive_count * page_size;
        
        *free = free_mem;
        *available = free_mem + inactive_mem; // Inactive can be reclaimed
        *used = *total - *available;
    } else {
        *used = 0;
        *free = 0;
        *available = 0;
    }
#elif __linux__
    FILE *fp = fopen("/proc/meminfo", "r");
    if (fp) {
        char line[256];
        unsigned long long mem_total = 0, mem_free = 0, mem_avail = 0, buffers = 0, cached = 0;
        while (fgets(line, sizeof(line), fp)) {
            if (sscanf(line, "MemTotal: %llu kB", &mem_total) == 1) mem_total *= 1024;
            else if (sscanf(line, "MemFree: %llu kB", &mem_free) == 1) mem_free *= 1024;
            else if (sscanf(line, "MemAvailable: %llu kB", &mem_avail) == 1) mem_avail *= 1024;
            else if (sscanf(line, "Buffers: %llu kB", &buffers) == 1) buffers *= 1024;
            else if (sscanf(line, "Cached: %llu kB", &cached) == 1) cached *= 1024;
        }
        fclose(fp);

        *total = mem_total;
        *free = mem_free;
        // If MemAvailable is not present (very old kernels), approximate it
        if (mem_avail == 0) {
            *available = mem_free + buffers + cached;
        } else {
            *available = mem_avail;
        }
        *used = *total - *free; // Standard "used" including cache
    } else {
        // Fallback to sysinfo
        struct sysinfo info;
        if (sysinfo(&info) == 0) {
            *total = (unsigned long long)info.totalram * info.mem_unit;
            *free = (unsigned long long)info.freeram * info.mem_unit;
            *available = *free + (unsigned long long)info.bufferram * info.mem_unit;
            *used = *total - *free;
        }
    }
#endif
}

void get_disk_stats(const char *path, unsigned long long *total, unsigned long long *used, unsigned long long *free) {
    struct statvfs stat;
    if (statvfs(path, &stat) != 0) {
        *total = *used = *free = 0;
        return;
    }
    
    *total = (unsigned long long)stat.f_blocks * stat.f_frsize;
    *free = (unsigned long long)stat.f_bavail * stat.f_frsize;
    *used = *total - *free;
}

int main() {
    double load1, load5, load15;
    unsigned long long mem_total, mem_used, mem_free, mem_available;
    unsigned long long disk_total, disk_used, disk_free;
    
    get_cpu_load(&load1, &load5, &load15);
    int cores = get_cpu_cores();
    get_memory_stats(&mem_total, &mem_used, &mem_free, &mem_available);
    get_disk_stats("/", &disk_total, &disk_used, &disk_free);

    double cpu_percent = (load1 / cores) * 100.0;
    if (cpu_percent > 100.0) cpu_percent = 100.0;
    
    double mem_used_percent = (mem_total > 0) ? ((double)mem_used / mem_total) * 100.0 : 0;
    double mem_apps_percent = (mem_total > 0) ? ((double)(mem_total - mem_available) / mem_total) * 100.0 : 0;
    double disk_percent = (disk_total > 0) ? ((double)disk_used / disk_total) * 100.0 : 0;

    printf("{\n");
    printf("  \"cpu\": {\n");
    printf("    \"cores\": %d,\n", cores);
    printf("    \"load_1m\": %.2f,\n", load1);
    printf("    \"load_5m\": %.2f,\n", load5);
    printf("    \"load_15m\": %.2f,\n", load15);
    printf("    \"usage_percent\": %.2f\n", cpu_percent);
    printf("  },\n");
    printf("  \"memory\": {\n");
    printf("    \"total\": %llu,\n", mem_total);
    printf("    \"used\": %llu,\n", mem_used);
    printf("    \"free\": %llu,\n", mem_free);
    printf("    \"available\": %llu,\n", mem_available);
    printf("    \"usage_percent\": %.2f,\n", mem_used_percent);
    printf("    \"apps_percent\": %.2f\n", mem_apps_percent);
    printf("  },\n");
    printf("  \"disk\": {\n");
    printf("    \"total\": %llu,\n", disk_total);
    printf("    \"used\": %llu,\n", disk_used);
    printf("    \"free\": %llu,\n", disk_free);
    printf("    \"usage_percent\": %.2f\n", disk_percent);
    printf("  }\n");
    printf("}\n");

    return 0;
}
