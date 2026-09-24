import { ApiRequest } from './ApiRequest';
import type { PaneShape } from './OrdersApi';

import type { ResponseContent, ResponseProps } from '@salvon/request';

export type ProductionStatus = 'pending' | 'in_progress' | 'done' | 'problem';

export type ProductionIssue = 'breakage' | 'material' | 'drawing' | 'rework';

export type ProductionRow = {
  id: number;
  order_id: number;
  order_number: number;
  contractor: string | null;
  deadline: string | null;
  /** Ujemna liczba to dni po terminie. */
  days_left: number | null;
  process: string | null;
  process_code: string | null;
  workstation: string | null;
  position: number;
  item: string | null;
  quantity: string | null;
  width_mm: number | null;
  height_mm: number | null;
  /** `null` przy usłudze — ta nie ma formatki. */
  shape: PaneShape | null;
  /** Pilna pozycja — wchodzi na poczatek kolejki. */
  is_urgent: boolean;
  /** Instrukcja technologiczna z pozycji zlecenia. */
  item_note: string | null;
  /** RAL, faza, rodzaj folii — powód, dla którego operator idzie pytać. */
  parameter: string | null;
  comment: string | null;
  list_comment: string | null;
  drawings: number;
  /** Postęp całego zlecenia — ile jego etapów jest zrobionych. */
  order_progress: { done: number; total: number; percent: number } | null;
  status: ProductionStatus;
  issue_type: ProductionIssue | null;
  note: string | null;
  started_at: string | null;
  finished_at: string | null;
  minutes_spent: number | null;
  by: string | null;
};

export type ProductionBoard = {
  /** `id: null` to etapy bez przypisanego stanowiska. */
  workstations: { id: number | null; name: string | null; open: number }[];
  rows: ProductionRow[];
  summary: {
    shown: number;
    /** Ile etapów pasuje do filtra — na wszystkich stronach. */
    total: number;
    page: number;
    pages: number;
    per_page: number;
    problems: number;
    overdue: number;
    as_of: string;
  };
};

export class ProductionApi extends ApiRequest {
  static prefix: string = '/production';

  public static async board(
    workstation: string = '',
    done: boolean = false,
    page: number = 1,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get('', {
      workstation,
      done: done ? '1' : '',
      page: String(page),
    });
  }

  public static async start(
    taskId: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/tasks/${taskId}/start`, {});
  }

  public static async finish(
    taskId: number,
    note: string = '',
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/tasks/${taskId}/finish`, { note });
  }

  public static async issue(
    taskId: number,
    issueType: ProductionIssue,
    note: string,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/tasks/${taskId}/issue`, {
      issue_type: issueType,
      note,
    });
  }

  public static async reopen(
    taskId: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/tasks/${taskId}/reopen`, {});
  }
}
