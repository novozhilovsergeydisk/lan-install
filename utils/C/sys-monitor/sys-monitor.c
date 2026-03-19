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

void get_memory_stats(unsigned long long *total, unsigned long long *used, unsigned long long *free) {
#ifdef __APPLE__
    int mib[2];
    int64_t physical_memory;
    size_t length;
    
    // Total memory
    mib[0] = CTL_HW;
    mib[1] = HW_MEMSIZE;
    length = sizeof(int64_t);
    sysctl(mib, 2, &physical_memory, &length, NULL, 0);
    *total = physical_memory;

    // Used/Free memory using mach
    vm_size_t page_size;
    mach_port_t mach_port;
    mach_msg_type_number_t count;
    vm_statistics64_data_t vm_stats;

    mach_port = mach_host_self();
    count = sizeof(vm_stats) / sizeof(natural_t);
    if (KERN_SUCCESS == host_page_size(mach_port, &page_size) &&
        KERN_SUCCESS == host_statistics64(mach_port, HOST_VM_INFO,
                                        (host_info64_t)&vm_stats, &count)) {
        
        long long free_memory = (int64_t)vm_stats.free_count * (int64_t)page_size;
        long long inactive_memory = (int64_t)vm_stats.inactive_count * (int64_t)page_size;
        
        *free = free_memory + inactive_memory;
        *used = *total - *free;
    } else {
        *used = 0;
        *free = 0;
    }
#elif __linux__
    struct sysinfo info;
    if (sysinfo(&info) == 0) {
        *total = (unsigned long long)info.totalram * info.mem_unit;
        // Basic calculation, true 'available' requires parsing /proc/meminfo
        *free = (unsigned long long)info.freeram * info.mem_unit;
        *used = *total - *free;
    } else {
        *total = *used = *free = 0;
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
    unsigned long long mem_total, mem_used, mem_free;
    unsigned long long disk_total, disk_used, disk_free;
    
    get_cpu_load(&load1, &load5, &load15);
    int cores = get_cpu_cores();
    get_memory_stats(&mem_total, &mem_used, &mem_free);
    get_disk_stats("/", &disk_total, &disk_used, &disk_free);

    // Calculate CPU usage percentage based on 1 min load average and core count
    // This is an approximation. A true % requires sampling ticks over time.
    double cpu_percent = (load1 / cores) * 100.0;
    if (cpu_percent > 100.0) cpu_percent = 100.0;
    
    double mem_percent = 0.0;
    if (mem_total > 0) {
        mem_percent = ((double)mem_used / mem_total) * 100.0;
    }

    double disk_percent = 0.0;
    if (disk_total > 0) {
        disk_percent = ((double)disk_used / disk_total) * 100.0;
    }

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
    printf("    \"usage_percent\": %.2f\n", mem_percent);
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
