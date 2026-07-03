// Before resolution:
int foo(int x) {     // -Wunused-parameter: x never used
    int x = 2;       // -Wshadow: shadows parameter
    {
        int x = 3;   // -Wshadow: shadows x from outer scope
    }
    return x;
}

// After resolution:
int foo(int x.1) {
    int x.2 = 2;
    {
        int x.3 = 3;
    }
    return x.2;
}
