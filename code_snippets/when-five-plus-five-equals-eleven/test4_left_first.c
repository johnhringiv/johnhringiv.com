int main(void) {
    int b = 0;
    // If left is evaluated first, b becomes 1 before right side
    // Result: 0 || 1 = true (returns 5)
    // If right is evaluated first: 0 || 0 = false (returns 7)
    if (b++ || b)
        return 5;
    return 7;
}