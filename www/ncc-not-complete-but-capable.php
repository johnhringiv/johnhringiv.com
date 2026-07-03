<?php
require_once "includes/classes.php";

$page_info = PageInfo::fromDB('ncc-not-complete-but-capable');

include_once "includes/top.php";
?>

<div class="container blog-post pb-2">
    <article>
        <?php $page_info->renderFullHeader(); ?>

        <div class="mb-5">
            <?php echo $page_info->html_description ?>
        </div>

        <div class="text-center my-4">
            <img src="<?='/img/blog/ncc_not_complete/ncc-not-complete-but-capable-v2.svg'?>"
                 alt="C source code transformed to x86-64 assembly by NCC"
                 class="img-fluid mx-auto d-block diagram-img">
        </div>

        <section>
            <h2 id="why-build-a-compiler" class="fw-bolder mb-4 mt-5">
                <a href="#why-build-a-compiler" class="text-reset text-decoration-none">Why Build a Compiler?</a>
            </h2>
            <p>
                I've always been interested in pulling back the layers of abstraction and understanding what my code is really doing under the hood.
                My day job as a Principal Data Scientist gives me plenty of interesting work but no systems programming, leaving me in need of a personal project.
                I wanted this project to pose fundamental Computer Science problems, provide an opportunity for programming creativity, and be something that could be meaningfully expanded over time.
                Putting this all together, I decided to write a compiler.
                Additionally, I have aspirations of putting some ideas and a lot of strong opinions into practice in the form of a new programming language;
                implementing a simpler one seemed to be a more logical entry point.
            </p>
            <p>
                This isn't completely new territory for me; I did take a compilers course (thanks <a href="https://www.uvm.edu/~jnear/">Joe Near</a>) in grad school not to mention core CS classes like Theory of Computation and Programming Languages with my advisor <a href="https://www.uvm.edu/cems/cs/profile/christian-skalka">Chris Skalka</a>.
                That said this project is quite a bit different in scope, and it's been a few years since I was in a classroom.
            </p>
        </section>

        <section>
            <h2 id="the-plan" class="fw-bolder mb-4 mt-5">
                <a href="#the-plan" class="text-reset text-decoration-none">The Plan</a>
            </h2>
            <p>
                With the decision to implement a compiler made there were two major undecided questions.
            </p>
            <ol>
                <li>What resource(s) to use</li>
                <li>What programming language for implementation</li>
            </ol>

            <h3 id="the-book" class="fw-bolder mb-3 mt-4">
                <a href="#the-book" class="text-reset text-decoration-none">The Book</a>
            </h3>
            <p>
                I didn't specifically start with making a C compiler as my goal; that decision was made by another.
                I reviewed the many compiler resources out there against my preferences:
            </p>
            <ul>
                <li>An Iterative Approach</li>
                <li>Targeting ASM (ideally AMD64)</li>
                <li>Focusing on the practical (not academic theory)</li>
                <li>Guiding but not spoon-feeding code</li>
                <li>No Racket or Lisp</li>
            </ul>
            <p>
                These preferences led me to one clear answer: <a href="https://norasandler.com/book/"><em>Writing a C Compiler</em></a> by Nora Sandler.
                This post is not a review of the book though I will say it is excellent.
                If you're an experienced programmer looking for a hands-on approach I'd say this book is for you; no existing compiler knowledge required.
                The book is more of a guide than tutorial; it gives you just enough context for you to figure out the implementation yourself and an itinerary to stay on track.
                If you want to deep dive on any given topic you'll need to read elsewhere but that's kinda the point: you're given enough to know what you don't know.
            </p>

            <h4 id="more-on-preferences" class="fw-bolder mb-3 mt-4">
                <a href="#more-on-preferences" class="text-reset text-decoration-none">More on Preferences</a>
            </h4>
            <p>
                Given my prior experience I did have a solid idea of what I wanted out of a resource.
                I'll take this opportunity to retro on them in case it helps others making similar decisions.
            </p>
            <p>
                <strong>Iterative Approach</strong>: Compilers are large and complex, leading most people who undertake such a project to abandon it.
                I like an iterative approach where you complete the full implementation of small features and build up the language organically.
                At the end of each stage you have something you can actually use and demo; plus the sense of accomplishment that comes with it.
                You know more is coming so your design is flexible, but you have clear opportunities to refactor as you expand and learn more.
                At risk of ruining the fun, it's as if the approach is <strong>Agile</strong>.
            </p>
            <p>
                <strong>Targeting ASM</strong>: I'm interested in the low-level and believe that going to that level of abstraction (yes even ASM is an abstraction) maximizes learning.
                I'm simply less interested in targeting byte-code or LLVM, though the latter is almost certainly the correct production choice if making a new language.
                Given that I program on an X86-64 machine targeting that made sense.
                ARM is a much cleaner architecture; if I developed primarily on a Mac I would target that.
            </p>
            <p>
                <strong>Practical Focus</strong>: Not much to say here except that I had a focus on writing software, not simply learning theory.
                I don't think one is better than the other; it's just how I wanted to spend my time.
            </p>
            <p>
                <strong>Guiding but not spoon-feeding</strong>: I picked this project wanting to write code and solve problems independently.
                I consider it a plus that the book doesn't give you more than snippets of pseudo code.
                For completeness, I'll note that the book does a reference implementation in OCaml; I haven't felt the need to look at it.
            </p>
            <p>
                <strong>No Racket or Lisp</strong>: These are a common choice for tutorial/hobby programming language development as they make parsing trivial.
                I've always had a strong distaste for the syntax, and the languages feel disconnected from what most associate with professional programming.
                To be clear there's nothing wrong with these I just personally wouldn't feel motivated to work on it.
            </p>

            <h4 id="why-implement-c" class="fw-bolder mb-3 mt-4">
                <a href="#why-implement-c" class="text-reset text-decoration-none">Why implement C</a>
            </h4>
            <p>
                The core of the C language is quite simple with no garbage collector or runtime and meets our requirement of going to ASM.
                This simplicity supports the incremental approach; other languages have a lot of interconnected features and concepts.
                C was designed to be portable assembly allowing a clear mental model of the transformation from AST to ASM.
                Nearly every language leverages C libraries for core functionality and most use C as their Foreign Function Interface for cross-language interoperability.
            </p>
            <p>
                Overall I'm very happy with the direction I took, but there are a few downsides.
            </p>
            <p>
                <strong>Type Systems</strong>: C is a weakly typed language.
                Strong typing would present much more learning opportunity.
                Increasingly rigorous type systems (e.g., Rust, Go) are the direction the field is headed.
                That said Programming Languages is a complex field in its own right distinct from Compilers and you can't do everything.
                This one can really be a pro or a con depending on your goals.
            </p>
            <p>
                <strong>Legacy Baggage</strong>: Sandler does a good job navigating around this for the most part but C has a lot of baggage.
                C's design is fundamentally incompatible with safety so a motivated individual can't even attempt to implement them without straying too far off course.
                Additionally, many of C's design decisions are poor or outdated so learning them to the detail required for implementation doesn't always feel worthwhile.
                I wouldn't go into a project like this with the goal of making a fully compliant C compiler; there's just too much uninteresting detail and complexity involved (e.g. the preprocessor).
                Knowing you'll never implement the full language can be a bit demotivating, but you do end up with a sizable subset.
                Hopefully I can find some real-word programs that fit within it.
            </p>
            <p>
                All that said, we have to implement something and C is far from a bad choice.
                For all its faults, C is relevant; it underpins virtually all modern languages and core libraries.
                In a way, if your goal in building a compiler is to understand how programs really work, you need to understand C: its calling conventions, memory layout, and so on.
                Perhaps learning C at a deep level will pay direct dividends; time will tell.
            </p>

            <h4 id="the-tests" class="fw-bolder mb-3 mt-4">
                <a href="#the-tests" class="text-reset text-decoration-none">The tests</a>
            </h4>
            <p>
                Following Sandler's book has one major advantage I didn't fully appreciate: The test suite.
                I know everyone says you need to test. You ignore them. The world keeps spinning.
                That's me too but when writing a compiler, you <em>really</em> need tests.
                Programming languages especially those with the baggage of C are loaded with edge cases that must be handled correctly.
                There are infinite combinations of tokens that construct valid programs, and you must handle them all correctly.
            </p>
            <p>
                The provided test suite allows the reader to follow TDD without the annoying part, writing tests.
                They were critical in keeping things moving along correctly.
                NCC passes the full Sandler test suite along with my personal additions that cover additional features and missed cases.
            </p>
        </section>

        <section>
            <h2 id="why-rust" class="fw-bolder mb-4 mt-5">
                <a href="#why-rust" class="text-reset text-decoration-none">Why Rust?</a>
            </h2>
            <p>
                Rust is a language that I deeply admire but haven't done any complex projects with.
                I won't go into a pro-Rust rant here except to say it provides incredible safety without compromising performance.
                A compiler can be written in any language and the best choice is usually the one you know best.
                In a vacuum OCaml is likely the best fit, though Rust is pretty close and I have both personal interest and professional use cases involving Rust (and neither for OCaml).
            </p>
            <p>
                Both Rust and OCaml have <strong>structural pattern matching</strong>, which I consider essential for not hating oneself during compiler development.
                ASTs are trees of variants: expressions can be binary operations, unary operations, variables, or constants.
                Pattern matching lets you destructure these cleanly and exhaustively:
            </p>
            <?php include "generated/highlighted-shiki/ncc-not-complete-but-capable/pattern_matching.html"; ?>
            <p>
                The compiler won't let you forget a variant. When you add a new expression type, every <code>match</code> that doesn't handle it becomes a compile error.
                Compare this to visitor patterns or <code>instanceof</code> chains in languages without pattern matching. It's night and day.
            </p>
            <p>
                <strong>Borrow Checker</strong>: The reason one may choose OCaml (or any particular language) over Rust is typically fear of the borrow checker.
                This has been a non-issue for me thus far. Maybe I have more <code>.clone()</code> than needed, but a garbage-collected language would have that overhead anyway.
                Recursive types ended up being a breeze with <code>Box&lt;Expr&gt;</code>. Things may become more difficult once I get to optimization passes, but based on my experience so far I'm not too worried.
                In any case, I firmly believe that the borrow checker's guarantees preventing bugs have saved me far more time than working within its constraints has cost me.
            </p>
            <p>
                <strong>Language Design</strong>: The irony isn't lost on me: learning a language designed for memory safety while implementing C, a language famous for letting you shoot yourself in the foot.
                The contrast between the two has been extremely illustrative and has become my guide in determining what I would want in my own design.
            </p>
            <p>
                It has not been easy implementing a language I don't know very well, in a language I don't know very well, on a topic I don't know very well.
                It has, however, been fun. Maybe now I do know some of those things well.
            </p>
        </section>

        <section>
            <h2 id="what-ncc-can-do" class="fw-bolder mb-4 mt-5">
                <a href="#what-ncc-can-do" class="text-reset text-decoration-none">What NCC Can Do Now</a>
            </h2>
            <p>
                After completing Part 1 (Chapters 1-10), NCC compiles a substantial subset of C:
            </p>
            <ul>
                <li><strong>Expressions</strong>: arithmetic, bitwise, comparison, logical (with short-circuit evaluation)</li>
                <li><strong>Variables</strong>: local and global, with block scoping and shadowing</li>
                <li><strong>Control flow</strong>: <code>if</code>/<code>else</code>, <code>while</code>, <code>do</code>-<code>while</code>, <code>for</code>, <code>switch</code>/<code>case</code>, <code>break</code>/<code>continue</code>, <code>goto</code></li>
                <li><strong>Functions</strong>: definitions, forward declarations, System V AMD64 calling convention</li>
                <li><strong>Storage classes</strong>: <code>static</code> (internal linkage), <code>extern</code> (external linkage)</li>
                <li><strong>Operators</strong>: compound assignment (<code>+=</code>, <code>-=</code>, <code>*=</code>...), increment/decrement (<code>++x</code>, <code>x++</code>), ternary (<code>?:</code>)</li>
            </ul>
            <h3 id="a-real-example" class="fw-bolder mb-3 mt-4">
                <a href="#a-real-example" class="text-reset text-decoration-none">A Real Example</a>
            </h3>
            <p>
                This <a href="https://en.wikipedia.org/wiki/Collatz_conjecture">Collatz Conjecture</a> program demonstrates static variables, functions, loops, bitwise operations, and conditionals:
            </p>
            <?php include "generated/highlighted-shiki/ncc-not-complete-but-capable/collatz.html"; ?>
            <p>
                NCC compiles this to working machine code showing that NCC is already capable of handling non-trivial programs.
            </p>
            <p>
                For completeness, here's the grammar:
            </p>
            <?php include "generated/highlighted-shiki/ncc-not-complete-but-capable/grammar.html"; ?>

            <h3 id="beyond-the-book" class="fw-bolder mb-3 mt-4">
                <a href="#beyond-the-book" class="text-reset text-decoration-none">Beyond the Book</a>
            </h3>
            <h4 id="extra-credit" class="fw-bolder mb-3 mt-4">
                <a href="#extra-credit" class="text-reset text-decoration-none">Extra Credit</a>
            </h4>
            <p>
                Several features in this list: <strong>bitwise operations</strong>, <strong>compound assignment</strong>, <strong>increment/decrement</strong>, <strong>goto</strong>, and <strong>switch statements</strong> were suggested but not covered by the book creating some fun challenges.
                Thankfully they were included in the test suite.
                I particularly struggled with switch statements which have, let's call it <em>interesting</em> behaviors, but we'll leave that for another time.
            </p>

            <h4 id="warnings" class="fw-bolder mb-3 mt-4">
                <a href="#warnings" class="text-reset text-decoration-none">Warnings</a>
            </h4>
            <p>
                Implementing a compiler gives you appreciation for the warnings real compilers provide.
                Sandler does not cover warnings in the book, compiler tutorials typically don't, though warning quality is typically a discerning feature of production compilers.
                Warnings can easily be the most complex part of the compiler as they may require detailed tracking and analysis of the program's control flow.
                As safety was not a consideration in C's design warnings are particularly hard to retro-fit into the language.
            </p>
            <p>
                NCC implements several:
            </p>
            <ul>
                <li><strong><code>-Wshadow</code></strong>: Warns when a variable shadows one from an outer scope</li>
                <li><strong><code>-Wunused-parameter</code></strong>: Warns about function parameters that are never used</li>
                <li><strong><code>-Wswitch-unreachable</code></strong>: Warns about code before the first case in a switch</li>
            </ul>
            <p>
                These are pretty basic but still help the programmer catch real bugs.
                I'll be able to implement more complex warnings after getting into optimization passes as they share a lot of tracking and analysis.
            </p>

            <h4 id="assembly" class="fw-bolder mb-3 mt-4">
                <a href="#assembly" class="text-reset text-decoration-none">Assembly</a>
            </h4>
            <p>
                Sandler follows the typical compiler tutorial pattern of emitting a text representation of assembly which can be passed off to GCC's or Clang's assembler.
                I decided I really wanted to have the full pipeline complete without calling out to external binaries, which we'll talk about in Pass 6 below.
            </p>
        </section>

        <section>
            <h2 id="the-architecture" class="fw-bolder mb-4 mt-5">
                <a href="#the-architecture" class="text-reset text-decoration-none">The Architecture</a>
            </h2>
            <p>
                NCC follows a classic multi-pass compiler pipeline:
            </p>

            <div class="text-center my-4">
                <img src="<?= versioned_url('/generated/mermaid/ncc-not-complete-but-capable/compiler-pipeline.svg') ?>"
                     alt="NCC Compiler Pipeline: Source .c → Lexer → Parser → Validator → Tackifier → Codegen → Emitter → Linker → Executable"
                     class="img-fluid responsive-img">
            </div>

            <ul class="list-unstyled">
                <li class="mb-3"><strong>Lexer</strong>: Converts source text into tokens using regex patterns with maximal munch. Each token carries a span for error reporting.</li>
                <li class="mb-3"><strong>Parser</strong>: Recursive descent with precedence climbing. Builds an abstract syntax tree while handling operator precedence.</li>
                <li class="mb-3"><strong>Validator</strong>: Two-pass semantic analysis: resolves variables to unique names, labels loops/switches, type checks, builds symbol table.</li>
                <li class="mb-3"><strong>Tackifier</strong>: Lowers the AST to TACKY, a three-address code IR. Flattens nested expressions and makes control flow explicit.</li>
                <li class="mb-3"><strong>Codegen</strong>: Converts TACKY to x86-64 assembly AST. Assigns pseudo-registers to stack slots and fixes invalid instructions.</li>
                <li class="mb-3"><strong>Emitter</strong>: Encodes instructions to machine code using <a href="https://github.com/icedland/iced">iced-x86</a> and writes ELF/Mach-O object files.</li>
                <li class="mb-3"><strong>Linker</strong>: Resolves external symbols and produces the final executable. Uses <a href="https://github.com/davidlattimore/wild">wild</a> on Linux.</li>
            </ul>

            <p>
                To illustrate, we'll trace this small program through each pass, showing how we iteratively transform source code into an executable:
            </p>
            <div class="shiki-bg-green">
            <?php include "generated/highlighted-shiki/ncc-not-complete-but-capable/example_program.html"; ?>
            </div>
            <p class="text-muted small mt-0"><em>All representations of this program below share this green-tinted background.</em></p>

            <h3 id="pass-1-lexer" class="fw-bolder mb-3 mt-4">
                <a href="#pass-1-lexer" class="text-reset text-decoration-none">Pass 1: Lexer</a>
            </h3>
            <p>
                The lexer converts source text into tokens using regex patterns. Nothing fancy, just a table of patterns mapped to token types:
            </p>
            <?php include "generated/highlighted-shiki/ncc-not-complete-but-capable/token_patterns.html"; ?>
            <p>
                The lexer tries all patterns and picks the longest match (maximal munch), so <code>interface</code> matches as an identifier (9 chars), not the <code>int</code> keyword (3 chars).
                Each token carries a <code>Span</code> (file, line, column) for error messages later.
            </p>
            <p>
                Our example becomes a flat stream of tokens:
            </p>
            <div class="shiki-bg-green">
            <?php include "generated/highlighted-shiki/ncc-not-complete-but-capable/token_stream.html"; ?>
            </div>

            <h3 id="pass-2-parser" class="fw-bolder mb-3 mt-4">
                <a href="#pass-2-parser" class="text-reset text-decoration-none">Pass 2: Parser</a>
            </h3>
            <p>
                The parser transforms the token stream into an Abstract Syntax Tree (AST).
            </p>
            <p class="lead shadow-sm py-2 ps-2 rounded-3">
                <b class="fs-5">Abstract Syntax Tree:</b> A tree structure representing the program's hierarchical structure where each node corresponds to a construct in the source code.
            </p>
            <p>
                NCC's parser uses <strong>recursive descent with precedence climbing</strong> to match the approach of production compilers.
                It's a common complaint that compiler discussions dedicate too much time to parsing; we'll try to avoid that error.
                Rolling your own parser is actually quite simple once you understand the core algorithm.
                The alternative is to use a parser generator like <strong>YACC</strong> and learn some automata theory.
                Recursive descent is easier to learn, debug, and reason about, while retaining full control over error messages and AST construction.
            </p>
            <p>
                For example, NCC peeks ahead to fuse <code>-</code> with integer literals so <code>-2147483648</code> parses correctly instead of overflowing as <code>-(2147483648)</code>.
                The parser also emits <code>-Wswitch-unreachable</code> when it detects statements before the first <code>case</code>.
                These context-sensitive behaviors would be awkward to express in a grammar file.
            </p>
            <p>
                <strong>Recursive descent</strong> handles statements and declarations. Each grammar rule becomes a function (<code>parse_statement()</code>, <code>parse_declaration()</code>, <code>parse_block()</code>) and parsing a construct means calling the function for that rule. When a rule references other rules, you call those functions, hence "descent." As you descend, you consume tokens from the front of the queue; when you return, the tokens for that construct are gone.
            </p>
            <p>
                <strong>Precedence climbing</strong> handles expressions. Instead of writing separate functions for each precedence level (15+ for C's operators), you write one function that takes a minimum precedence parameter.
                Parse the left operand, then loop: if the next operator's precedence is high enough, consume it and recurse for the right side.
                Higher-precedence operators bind tighter because they get parsed in deeper recursive calls.
            </p>
            <p>
                For example, parsing <code>a + b * c</code>: when we see <code>+</code> (precedence 45), we recurse with <code>min_prec=46</code>. The <code>*</code> (precedence 50) is higher, so it gets consumed in that recursive call.
                The recursion returns <code>b * c</code>, and we build <code>a + (b * c)</code>.
            </p>
            <p>
                Our example program parses to the AST below. Note how our representation now has the structure of the language.
            </p>
            <div class="shiki-bg-green">
            <?php include "generated/highlighted-shiki/ncc-not-complete-but-capable/ast_tree.html"; ?>
            </div>

            <h3 id="pass-3-validator" class="fw-bolder mb-3 mt-4">
                <a href="#pass-3-validator" class="text-reset text-decoration-none">Pass 3: Validator (Semantic Analysis)</a>
            </h3>
            <p>
                After parsing, we know the program is syntactically valid. Now we verify its semantics in two passes over the parser's AST.
            </p>

            <h4 id="resolution-pass" class="fw-bolder mb-3 mt-4">
                <a href="#resolution-pass" class="text-reset text-decoration-none">Resolution pass</a>
            </h4>
            <p>
                <strong>Variable resolution</strong>: Variables are renamed for uniqueness.
                Two variables might be syntactically the same (both named <code>x</code>) but semantically refer to different objects.
            </p>
            <p>
                <strong>Loop and switch labeling</strong>: Break and continue statements need to know which loop they belong to. The validator annotates each loop with a unique label number, and each break/continue with its target.
            </p>
            <p>
                <strong>goto/label validation</strong>: We verify that all <code>goto</code> statements target labels that exist in the same function.
            </p>
            <p>
                <strong>Warnings</strong>: NCC provides warnings in select cases of valid but often undesirable programs.
            </p>
            <p>
                Here's how variable resolution transforms a function with shadowing:
            </p>
            <?php include "generated/highlighted-shiki/ncc-not-complete-but-capable/variable_resolution.html"; ?>
            <p>
                After this pass, the compiler no longer needs to track scoping; each name is globally unique. The warnings are emitted during resolution as we detect each shadowing occurrence.
            </p>

            <h4 id="typecheck-pass" class="fw-bolder mb-3 mt-4">
                <a href="#typecheck-pass" class="text-reset text-decoration-none">Typecheck pass</a>
            </h4>
            <p>
                <strong>Type checking</strong>: Currently NCC supports a single type, <code>int</code>. Even so, there's work to do.
                We ensure functions aren't used as variables and vice versa, check that function declarations don't conflict, and verify call sites have the correct number of arguments.
            </p>
            <p>
                <strong>Building the symbol table</strong>: Functions and global variables get recorded with their types, linkage (<code>static</code> vs <code>extern</code>), and whether they've been defined. Codegen uses this table to determine which symbols need relocations and which are local to this translation unit.
            </p>
            <p>
                <strong>Constant expression evaluation</strong>: Case labels and static variable initializers must be compile-time constants.
                We recursively evaluate these expressions during validation, folding operations like <code>1 + 2</code> into their result.
            </p>
            <p>
                A note on C's overloaded keywords: <code>static</code> means "internal linkage" at file scope but "static storage duration" inside a function. <code>extern</code> means "defined elsewhere" but can also just be a declaration without definition. Implementing these correctly meant carefully reading the spec and testing against GCC's behavior.
            </p>
            <p>
                For this simple example the AST structure is unchanged, but loops would be annotated with labels for break/continue resolution and variables renamed.
                The validator builds a symbol table: <code>counter</code> is marked as static (internal linkage, not visible to other files), while <code>next</code> and <code>main</code> are global.
                The initializer <code>0</code> is evaluated as a constant expression.
                After passing this stage we know that the program is valid, the AST didn't change much but the work here allows a big leap in the next pass.
            </p>

            <h3 id="pass-4-tackifier" class="fw-bolder mb-3 mt-4">
                <a href="#pass-4-tackifier" class="text-reset text-decoration-none">Pass 4: Tackifier (AST → TACKY IR)</a>
            </h3>
            <p>
                TACKY is a three-address code IR where complex expressions become sequences of simple operations:
            </p>
            <?php include "generated/highlighted-shiki/ncc-not-complete-but-capable/tacky_simple.html"; ?>
            <p>
                <strong>Why bother with an IR?</strong> Two reasons:
            </p>
            <p>
                <strong>1. Expressions become trivial.</strong> The AST has nested expressions; TACKY has flat sequences. Code generation doesn't need to think about operator precedence or temporary management.
            </p>
            <p>
                <strong>2. Control flow becomes explicit.</strong> An <code>if</code> statement becomes jumps and labels:
            </p>
            <?php include "generated/highlighted-shiki/ncc-not-complete-but-capable/tacky_if.html"; ?>
            <p>
                The tackifier walks the AST recursively, emitting instructions into a flat list. A <code>NameGenerator</code> produces unique temporary names (<code>temp.1</code>, <code>temp.2</code>, etc.).
            </p>
            <p>
                Here's our example lowered to TACKY. Notice how nested expressions are now flat sequences of simple operations.
            </p>
            <div class="shiki-bg-green">
            <?php include "generated/highlighted-shiki/ncc-not-complete-but-capable/tacky_example.html"; ?>
            </div>
            <p>
                The postfix increment <code>counter++</code> becomes explicit: save the original, compute the new value, store it back. In <code>main</code>, the nested expression <code>next() + next()</code> is flattened and each function call result is captured in a temporary before the add.
            </p>

            <h3 id="pass-5-codegen" class="fw-bolder mb-3 mt-4">
                <a href="#pass-5-codegen" class="text-reset text-decoration-none">Pass 5: Codegen (TACKY → Assembly AST)</a>
            </h3>
            <p>
                This pass converts TACKY instructions to an <strong>assembly AST</strong>, a structured representation of x86-64 instructions.
            </p>
            <p>
                The conversion is mostly straightforward: TACKY's <code>Binary { Add, src1, src2, dst }</code> becomes x86's <code>mov src1, dst; add src2, dst</code>. But there are complications:
            </p>
            <p>
                <strong>Pseudo-registers</strong>: Initially, all variables are <code>Pseudo("x.1")</code> operands. This lets us generate instructions without knowing the data's canonical location; a later iteration in this pass assigns stack slots or registers:
            </p>
            <?php include "generated/highlighted-shiki/ncc-not-complete-but-capable/pseudo_register.html"; ?>
            <p>
                <strong>Fixing invalid instructions</strong>: x86-64 has restrictions. You can't <code>mov</code> from memory to memory, or <code>idiv</code> an immediate.
                A fixup pass rewrites these:
            </p>
            <?php include "generated/highlighted-shiki/ncc-not-complete-but-capable/fix_instructions.html"; ?>
            <p>
                <strong>Calling convention</strong>: Function calls follow System V AMD64 ABI. The first six integer arguments go in RDI, RSI, RDX, RCX, R8, R9; the rest go on the stack. The stack must be 16-byte aligned before <code>call</code>.
            </p>
            <p>
                The <code>next</code> function's assembly AST shows pseudo-registers replaced with stack slots and <code>counter</code> using RIP-relative addressing (accessing data relative to the instruction pointer, required for position-independent code):
            </p>
            <div class="shiki-bg-green">
            <?php include "generated/highlighted-shiki/ncc-not-complete-but-capable/asm_ast.html"; ?>
            </div>

            <h3 id="pass-6-emitter" class="fw-bolder mb-3 mt-4">
                <a href="#pass-6-emitter" class="text-reset text-decoration-none">Pass 6: Emitter (Assembly → Machine Code)</a>
            </h3>
            <p>
                This is where NCC diverges from most tutorials. Instead of emitting assembly text and shelling out to <code>as</code>, we use <strong>iced-x86</strong> to encode instructions directly:
            </p>
            <?php include "generated/highlighted-shiki/ncc-not-complete-but-capable/iced_x86.html"; ?>
            <p>
                For working with X86-64 I recommend using a library or sticking with emitting textual asm as instruction sizes vary making it difficult to encode.
                In contrast, I've heard assembling ARM is much more straightforward and doable as a hobby project.
            </p>
            <p>
                The <code>object</code> crate then wraps the machine code in ELF (Linux) or Mach-O (macOS) format, handling relocations for external symbols.
            </p>
            <p>
                <strong>Why do this?</strong>
            </p>
            <ul>
                <li>Single binary: no dependency on external tools</li>
                <li>Faster compilation: no spawning subprocesses</li>
                <li>Educational: you understand exactly what bytes end up in the executable</li>
            </ul>
            <p>
                The tradeoff is complexity. When you emit text assembly, the assembler handles instruction encoding, relocation generation, and symbol table construction. With iced-x86, you handle these yourself: ensuring RIP-relative addressing for static variables, calculating relocation addends based on instruction format, setting correct symbol visibility for cross-file linking, and building object files with proper sections and relocations. (That's a whole other blog post.)
            </p>
            <p>
                NCC can pretty-print the assembly for debugging, using the symbol table to resolve function and global variable names back to their labels:
            </p>
            <div class="shiki-bg-green">
            <?php include "generated/highlighted-shiki/ncc-not-complete-but-capable/final_asm.html"; ?>
            </div>
            <p>
                We made it from C to x86-64!
                The observant reader may notice that there's a few unnecessary moves here.
                This is because NCC uses the <strong>load-execute-store</strong> pattern which treats the stack as the canonical location only using registers as scratch space for individual operations.
                This assembly will look a bit different, and be more efficient, once register allocation and copy propagation are implemented.
            </p>

            <h3 id="pass-7-linker" class="fw-bolder mb-3 mt-4">
                <a href="#pass-7-linker" class="text-reset text-decoration-none">Pass 7: Linker</a>
            </h3>
            <p>
                On Linux, NCC uses <strong>libwild</strong> for linking, a Rust linker that can produce executables without calling the system <code>ld</code>.
                On macOS, we shell out to <code>ld</code> as libwild does not support it.
            </p>
            <p>
                The linker resolves external symbols (like <code>putchar</code> from libc), combines object files, and produces the final executable.
            </p>

            <h3 id="why-so-many-passes" class="fw-bolder mb-3 mt-4">
                <a href="#why-so-many-passes" class="text-reset text-decoration-none">Why So Many Passes</a>
            </h3>
            <p>
                <strong>Each pass should do one thing.</strong> Compilers are complex but writing them doesn't have to be.
                Each pass either transforms the program representation closer to the target or checks it for correctness.
                As complexity grows so will the number of passes; individual optimizations may even be their own pass.
            </p>
        </section>

        <section>
            <h2 id="lessons-learned" class="fw-bolder mb-4 mt-5">
                <a href="#lessons-learned" class="text-reset text-decoration-none">Lessons Learned</a>
            </h2>
            <p>
                Building a compiler forces you to learn plenty of things; I'll skip the obvious and touch on some high-level concepts.
            </p>
            <ul>
                <li><strong>Programming Language Design</strong>: Seeing the faults of C while working with Rust's constraints and guarantees has shaped my thinking on what makes a good language</li>
                <li><strong>Abstractions</strong>: Difficult problems can be more easily handled with layers of abstraction, sometimes you need a few.</li>
                <li><strong>Understanding C</strong>: It's not all about ASM. C's standards (e.g. calling convention, stack layout, linking) underpin the modern languages we use daily.</li>
                <li><strong>Have a Plan</strong>: Complex hobby projects usually end up abandoned. Having a clear focus can help. The incremental approach is key, picking the project back up after a few months never felt daunting.</li>
            </ul>
            <p>
                If you're interested in compilers, systems programming, or just want to flex your Computer Science skills I recommend you tackle a similar project.
                The book is excellent, and the learning is unmatched.
            </p>
        </section>

        <section>
            <h2 id="whats-next" class="fw-bolder mb-4 mt-5">
                <a href="#whats-next" class="text-reset text-decoration-none">What's Next</a>
            </h2>
            <p>
                There are two parts of the book left, making the next step clear.
                In Part II I'll be adding more complex types including pointers, arrays, and structs.
                Part III will add register allocation and some optimization passes.
            </p>
            <p>
                After the book there's a few directions I could go.
            </p>
            <p>
                <strong>Optimizations</strong>:
                I'll likely convert TACKY to Static Single Assignment (SSA) form and add some more complex optimizations like sparse conditional constant propagation (SCCP), loop-invariant code motion, and common subexpression elimination.
                I've also been interested in auto-vectorization and could see myself implementing it; TSVC (Test Suite for Vectorizing Compilers) would be a good benchmark to work toward.
            </p>
            <p>
                <strong>Language Design</strong>:
                Ultimately I'd like to make my own language and have been firming up exactly what I want that to look like.
                I would likely implement it using NCC's backend (though learning LLVM would be a practical choice) so it complements the first item.
            </p>
            <p>
                As far as the blog goes I may do some deeper dives on some of the topics covered here including specific feature implementations (e.g. switch statements), interesting bugs I encountered, and thoughts on language design.
            </p>
            <hr class="my-4">
            <p>
                <em>NCC is open source and available on <a href="https://github.com/johnhringiv/NCC-Rust">GitHub</a>.</em>
            </p>
        </section>

        <?php include("includes/say_hello.html") ?>
    </article>
</div>
<?php include "includes/footer.php"; ?>
