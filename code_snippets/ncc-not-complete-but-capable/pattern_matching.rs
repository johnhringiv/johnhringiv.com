// shiki: nolinenum
match expr {
    Expr::Binary(op, left, right) => { /* handle binary */ }
    Expr::Unary(op, operand) => { /* handle unary */ }
    Expr::Var(name) => { /* handle variable */ }
    Expr::Constant(val) => { /* handle constant */ }
} // Rust ensures you handle every case
