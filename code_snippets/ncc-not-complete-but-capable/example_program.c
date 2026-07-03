static int counter = 0;

int next(void) {
    counter++;
    return counter;
}

int main(void) {
    return next() + next();
}
