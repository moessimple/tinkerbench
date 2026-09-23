export * from './auth';

export interface ExceptionFrame {
    file: string;
    function: string | null;
    line: number;
    snippet: boolean;
    vendor: boolean;
}

export type FeedItem =
    | { html: string; kind: 'dump'; line: number | null; text: string }
    | { html: string; kind: 'result'; text: string }
    | {
          connection: string;
          duplicate: boolean;
          duration_ms: number;
          duration_str: string;
          kind: 'query';
          line: number | null;
          slow: boolean;
          sql: string;
      }
    | {
          context_html: string | null;
          context_text: string | null;
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
      }
    | {
          count: number;
          kind: 'n_plus_one';
          line: number | null;
          model: string;
          relation: string;
      }
    | {
          data_html: string;
          data_text: string;
          kind: 'view';
          line: number | null;
          path: string;
      }
    | {
          duration_ms: number;
          duration_str: string;
          faked: boolean;
          kind: 'http_client';
          line: number | null;
          method: string;
          request_body_preview: string | null;
          request_content_type: string | null;
          request_headers: Record<string, string[]>;
          request_size: number | null;
          request_truncated: boolean;
          request_type: string;
          response_body_preview: string | null;
          response_content_type: string | null;
          response_headers: Record<string, string[]>;
          response_size: number | null;
          response_truncated: boolean;
          status: number;
          url: string;
      };

export interface SnippetDebugPayload {
    boot_duration_ms: number;
    boot_duration_str: string;
    duplicate_query_count: number;
    http_duration_ms: number;
    http_duration_str: string;
    http_request_count: number;
    items: FeedItem[];
    php_duration_ms: number;
    php_duration_str: string;
    peak_memory_str: string;
    query_count: number;
    query_duration_ms: number;
    query_duration_str: string;
    run_duration_ms: number;
    run_duration_str: string;
}
