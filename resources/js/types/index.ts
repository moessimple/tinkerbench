export * from './auth';

export interface DebugQueryStatement {
    connection: string;
    duration_str: string | null;
    params: unknown[];
    sql: string;
}

export interface DebugTimeMeasure {
    duration_str: string;
    label: string;
}

export interface DebugException {
    file: string;
    line: number;
    message: string;
    type: string;
}

export interface DebugMessage {
    label: string;
    message: string | null;
}

export interface SnippetDebugPayload {
    exceptions?: {
        count: number;
        exceptions: DebugException[];
    };
    logs?: {
        count: number;
        messages: DebugMessage[];
    };
    memory?: {
        peak_usage: number;
        peak_usage_str: string;
    };
    messages?: {
        count: number;
        messages: DebugMessage[];
    };
    queries?: {
        count: number;
        statements: DebugQueryStatement[];
    };
    time?: {
        duration_str: string;
        measures: DebugTimeMeasure[];
    };
}
