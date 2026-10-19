export type Stats = {
    progress: number;
    due_cards: number;
    streak: number;
};

export type Progress = {
    done: number;
    total: number;
    pct: number;
};

export type ModuleOption = {
    slug: string;
    title: string;
};

export type Concept = {
    id: number;
    body: string;
    completed: boolean;
};

export type RoadmapModule = {
    id: number;
    slug: string;
    position: number;
    section: string;
    command: string;
    title: string;
    progress: Progress;
    concepts: Concept[];
};

export type Flashcard = {
    id: number;
    question: string;
    answer: string;
    module: string;
    box: number;
};

export type QuizQuestion = {
    id: number;
    question: string;
    options: string[];
    correct_index: number;
    explanation: string;
    module: string;
};

export type Challenge = {
    id: number;
    body: string;
    completed: boolean;
    module: { title: string; command: string };
};

export type InterviewQuestion = {
    id: number;
    question: string;
    points: string[];
    completed: boolean;
    module: string;
};

export type LearnModuleSummary = {
    slug: string;
    position: number;
    section: string;
    command: string;
    title: string;
    lessons: { total: number; read: number };
    resources: number;
};

export type LearnModuleHeader = {
    slug: string;
    position: number;
    command: string;
    title: string;
};

export type LessonSummary = {
    slug: string;
    title: string;
    summary: string;
    minutes: number;
    completed: boolean;
};

export type LessonDetail = {
    id: number;
    slug: string;
    title: string;
    summary: string;
    minutes: number;
    html: string;
    completed: boolean;
};

export type LessonLink = {
    slug: string;
    title: string;
};

export type ResourceItem = {
    id: number;
    title: string;
    url: string;
    provider: string;
    language: string;
    is_free: boolean;
    note: string;
    embed_url: string | null;
};

export type ResourceGroup = {
    kind: string;
    label: string;
    items: ResourceItem[];
};

export type StackCategory = {
    value: string;
    label: string;
};

export type StackSummary = {
    slug: string;
    name: string;
    summary: string;
    category: string;
    headline: string[];
    reviewed_on: string;
};

export type StackDetail = {
    slug: string;
    name: string;
    summary: string;
    category: StackCategory;
    when_to_use: string[];
    when_to_avoid: string[];
    pros: string[];
    cons: string[];
    also_learn: string[];
    sources: { title: string; url: string }[];
    reviewed_on: string;
};

export type StackLayerGroup = {
    layer: string;
    label: string;
    items: { name: string; note: string }[];
};

export type StackModule = {
    slug: string;
    position: number;
    command: string;
    title: string;
    progress: Progress;
};

export type WizardOption = {
    value: string;
    label: string;
};

export type WizardQuestion = {
    key: string;
    title: string;
    help: string | null;
    multiple: boolean;
    options: WizardOption[];
};

export type WizardAnswers = {
    clients: string | null;
    team: string | null;
    ui: string | null;
    extras: string[];
};

export type WizardCard = {
    slug: string;
    name: string;
    summary: string;
    category: string;
    headline: string[];
    why: string;
};

export type WizardResult = {
    base: WizardCard[];
    addons: WizardCard[];
    preparation: {
        readiness: number;
        modules: number;
        startWith: { slug: string; title: string; pct: number }[];
    };
};

export type CompareColumn = {
    slug: string;
    name: string;
    summary: string;
    category: string;
    headline: string[];
    when_to_use: string[];
    when_to_avoid: string[];
    pros: string[];
    cons: string[];
    readiness: number;
    modules: number;
    layers: Record<string, string[]>;
};

export type JobAnalysisItem = {
    id: number;
    title: string | null;
    created_at: string | null;
    skills: number;
};

export type JobModule = {
    slug: string;
    title: string;
    section: string;
    command: string;
    status: 'ready' | 'learning' | 'todo';
    mentions: number;
    progress: Progress;
    skills: { name: string; mentions: number; note: string | null }[];
};

export type JobOutsideSkill = {
    name: string;
    mentions: number;
    category: string | null;
    hint: string | null;
};

export type JobReport = {
    summary: {
        detected: number;
        covered: number;
        outside: number;
        readiness: number | null;
    };
    modules: JobModule[];
    outside: JobOutsideSkill[];
};

export type ProjectLevel = {
    value: string;
    label: string;
};

export type ProjectSummary = {
    slug: string;
    title: string;
    level: ProjectLevel;
    hours: number;
    summary: string;
    stack: { slug: string; name: string } | null;
    modules: number;
    progress: Progress;
};

export type ProjectDetail = {
    slug: string;
    title: string;
    level: ProjectLevel;
    hours: number;
    summary: string;
    deliverables: string[];
    stretch: string[];
    stack: { slug: string; name: string } | null;
    progress: Progress;
};

export type ProjectMilestone = {
    id: number;
    title: string;
    detail: string;
    completed: boolean;
};

export type ProjectModule = {
    slug: string;
    title: string;
    command: string;
    progress: Progress;
};
