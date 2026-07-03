// shiki: nolinenum
fn replace_pseudo(&mut self, operand: &Operand) -> Operand {
    match operand {
        Operand::Pseudo(name) => {
            if self.data_vars.contains(name) {
                Operand::Data(name.clone())  // Global: RIP-relative
            } else {
                self.get_stack_location(name)  // Local: stack slot
            }
        }
        _ => operand.clone(),
    }
}
