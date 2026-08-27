export * from './auth';

export interface ExceptionFrame {
    file: string;
    function: string | null;
    line: number;
    snippet: boolean;
    vendor: boolean;
}

export type FeedItem =
    | { html: string; kind: 'dump'; line: number | null }
    | {
          connection: string;
          duplicate: boolean;
          duration_str: string;
          kind: 'query';
          line: number | null;
          slow: boolean;
          sql: string;
      }
    | {
          context: string | null;
          kind: 'log';
          label: string;
          line: number | null;
          message: string;
      }
    | {
          frames: ExceptionFrame[];
          kind: 'exception';
          line: number | null;
          message: string;
          type: string;
      };

export interface SnippetDebugPayload {
    duration_str: string;
    items: FeedItem[];
    peak_memory_str: string;
}
