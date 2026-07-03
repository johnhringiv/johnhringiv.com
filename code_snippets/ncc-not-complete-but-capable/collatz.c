// Which starting number under 100 takes the most steps to reach 1?

static int total_steps = 0;

int collatz(int n) {
    int steps = 0;
    while (n != 1) {
        if (n & 1) {
            n = 3 * n + 1;    // odd
        } else {
            n = n >> 1;       // even: divide by 2
        }
        steps++;
    }
    total_steps += steps;
    return steps;
}

int main(void) {
    int champion = 1;
    int max_steps = 0;

    for (int i = 1; i < 100; i++) {
        int steps = collatz(i);
        if (steps > max_steps) {
            max_steps = steps;
            champion = i;
        }
    }
    return champion;  // Returns 97 (118 steps!)
}
