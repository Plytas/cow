#include <sys/clonefile.h>
#include <stdio.h>

int main(int argc, char **argv) {
    if (argc != 3) {
        fprintf(stderr, "usage: %s src dst\n", argv[0]);
        return 2;
    }

    if (clonefile(argv[1], argv[2], 0) != 0) {
        perror("clonefile");
        return 1;
    }

    return 0;
}
