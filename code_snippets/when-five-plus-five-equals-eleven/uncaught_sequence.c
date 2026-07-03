#include <stdio.h>

int f(int *p, int val) {
    *p = val;
    return val;
}

int main(void) {
    int i = 1;
    int result = f(&i, 2) + f(&i, 3);  // Undefined: order of function calls
    printf("result = %d, i = %d\n", result, i);
    return 0;
}