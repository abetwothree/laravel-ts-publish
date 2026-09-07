// Stubs standing in for the consuming app's own types - the modules `#[TsCasts]` / `#[TsExtends]` point at,
// which this package never writes - so the generated imports resolve and the token gate runs at a zero baseline.
// Hand-maintained on purpose: deriving them from the generated tree would check that tree against itself.

// Emitted as bare names because neither source carries an FQCN or import path: a docblock array shape
// (`custom_val: CustomObject`) and a bare `#[TsExtends('ExtendableInterface')]` with no import argument.
declare global {
    interface CustomObject {}

    interface ExtendableInterface {}
}

export {};
