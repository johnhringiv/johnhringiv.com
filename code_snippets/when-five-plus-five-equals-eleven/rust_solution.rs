// Before (buggy):
parser::Expr::Binary(op, e1, e2) => {
    let src1 = tackify_expr(e1, instructions, name_generator);
    let src2 = tackify_expr(e2, instructions, name_generator);
    // src1 is just a reference - will use the current value when executed
    ...
}

// After (fixed):
parser::Expr::Binary(op, e1, e2) => {
    let mut src1 = tackify_expr(e1, instructions, name_generator);
    // If src1 is a variable, copy it to capture its current value
    if let Val::Var(_) = &src1 {
        let temp = Val::Var(name_generator.next("binary_left"));
        instructions.push(Instruction::Copy {
            src: src1.clone(),
            dst: temp.clone(),
        });
        src1 = temp;  // Use the captured value
    }
    let src2 = tackify_expr(e2, instructions, name_generator);
    ...
}