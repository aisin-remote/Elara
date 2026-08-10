# Orbitra project instructions

- Treat `D:\fabian\00_Project\AI\isma\docs\flowza-project\PRD_Orbitra_Project_Management_SaaS.md` as the product source of truth.
- Treat `D:\fabian\00_Project\AI\isma\docs\flowza-project\Prompt_Teknis_Orbitra_Laravel10_Blade.txt` as the technical source of truth.
- Before planning, reviewing, or changing Orbitra, read both files completely. A later explicit user instruction wins if it conflicts.
- Keep all Orbitra implementation inside `D:\fabian\00_Project\AI\orbitra`. Never modify or mix code from the Nucleus application in `isma`.
- Preserve Laravel 10, PHP 8.2 compatibility, MySQL 8, Blade, Tailwind CSS, Alpine.js, manual session authentication, Policies, Form Requests, ULID public routing, and the documented test requirements.
- Do not use Breeze, Fortify, Jetstream, Inertia, React, Vue, Livewire, shadcn, a SPA framework, or microservices.
- Work phase by phase in the documented order and stop at each checkpoint unless the user explicitly requests the next phase.
- Do not leave fake buttons, TODOs, placeholder handlers, hard-coded production metrics, unsafe public files, or sequential public identifiers.
- After every phase, run and report the actual results of `php artisan test`, `vendor/bin/pint --test`, and `npm run build`.
