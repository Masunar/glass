import { ApiRequest } from './ApiRequest';

import type { ResponseContent, ResponseProps } from '@salvon/request';

export type NextStep = {
  transition_id: number;
  to_status: string;
  to_status_code: string;
  label: string;
  available: boolean;
  /** Powód blokady — pusty, gdy przejście jest dostępne. */
  blocked_by: string | null;
  /** Warunku nie da się sprawdzić, bo brakuje modułu, nie danych. */
  unknown: boolean;
  /** Brakuje tylko powodu — da się go podać razem z akcją. */
  needs_reason: boolean;
};

export type OrderRow = {
  id: number;
  number: number;
  created_at: string | null;
  contractor: string | null;
  contractor_phone: string | null;
  note: string | null;
  status: string | null;
  status_code: string | null;
  deadline: string | null;
  /** Ujemna liczba to dni po terminie. */
  days_left: number | null;
  is_shifted: boolean;
  delivery_method: string;
  delivery_place: string | null;
  amount: string;
  owner_initials: string | null;
  is_on_hold: boolean;
  hold_reason: string | null;
  has_open_claim: boolean;
  next_step: NextStep | null;
  blocked_step: NextStep | null;
};

export type OrderBandKey = 'today' | 'overdue' | 'later';

export type OrderBoard = {
  bands: {
    key: OrderBandKey;
    count: number;
    total: string;
    rows: OrderRow[];
  }[];
  filters: { code: string | null; name: string; count: number }[];
  summary: {
    today: number;
    overdue: number;
    shown: number;
    as_of: string;
  };
};

export type OrderPane = {
  width_mm: number;
  height_mm: number;
  is_irregular_shape: boolean;
  is_tempered: boolean;
  needs_mark: boolean;
};

export type OrderCardItem = {
  id: number;
  section: string;
  name: string;
  quantity: string;
  /** `null` znaczy brak pozycji w cenniku, nie cene zerowa. */
  unit_net_price: string | null;
  amount: string;
  processes: string[];
  pane: OrderPane | null;
};

export type OrderCardList = {
  id: number;
  number: number;
  name: string | null;
  role: 'component' | 'alternative';
  /** Wyłączona nie należy do zlecenia; wstrzymana należy, ale nie idzie dalej. */
  is_included: boolean;
  is_on_hold: boolean;
  /** `null` = jak w typie faktury. Nie to samo co `0`. */
  vat_rate: number | null;
  comment: string | null;
  net: string;
  items: OrderCardItem[];
};

export type OrderPathStep = {
  code: string;
  name: string;
  is_subcontracted: boolean;
  items: number;
  amount: string;
  /** `null` = zlecenie nie było jeszcze na produkcji, a nie „zero zrobione". */
  done: number | null;
  tasks: number | null;
  problems: number;
};

export type OrderHistoryEntry = {
  at: string | null;
  event: string;
  user: string | null;
  changes: { field: string; before: unknown; after: unknown }[];
};

export type OrderTabCounts = {
  panes: number;
  drawings: number;
  payments: number;
  log: number;
};

export type OrderCard = {
  order: {
    id: number;
    number: number;
    created_at: string | null;
    created_by: string | null;
    status: string | null;
    status_code: string | null;
    is_final: boolean;
    is_on_hold: boolean;
    hold_reason: string | null;
    has_open_claim: boolean;
    /** Dni najdłuższej formatki. `null` = nie ma z czego liczyć. */
    estimated_days: number | null;
    contractor: {
      id: number;
      name: string;
      display_name: string;
      tax_id: string | null;
      phone: string | null;
      email: string | null;
      address: string | null;
      city: string | null;
      contact: string | null;
    } | null;
    delivery: {
      method: string;
      place: string | null;
      address: string | null;
      contact: string | null;
    };
    invoice: {
      type: string | null;
      vat_rate: number | null;
      buyer_name: string | null;
      buyer_tax_id: string | null;
      buyer_address: string | null;
      accounting_note: string | null;
    };
    /** `null` = zwykła sprzedaż, bez budownictwa mieszkaniowego. */
    investment: InvestmentVat | null;
    deadline: {
      client: string | null;
      production: string | null;
      shifted: string | null;
      shift_reason: string | null;
      effective: string | null;
      days_left: number | null;
    };
    comments: {
      short: string | null;
      production: string | null;
      installer: string | null;
      offer: string | null;
    };
  };
  tabs: OrderTabCounts;
  money: OrderTotals;
  payment: OrderPaymentSummary;
  credit: {
    limit: string;
    payment_days: number;
    order_value: string;
    /** Cały dług kontrahenta, nie tylko to zlecenie. */
    outstanding: string;
    exceeds_by: string | null;
    is_gross: boolean;
  } | null;
  steps: NextStep[];
  path: OrderPathStep[];
  /**
   * Stan hartowania zlecenia. `null` znaczy, że nie ma nic do
   * hartowania — wtedy karta o tym nie wspomina.
   */
  tempering: {
    /** Czeka, czyli zlecenie nie przejdzie na „Gotowe". */
    is_waiting: boolean;
    queued: number;
    sent: number;
    returned: number;
    broken: number;
    /** Najdłużej jadąca partia, w dniach od wysyłki. */
    days_out: number | null;
    batches: {
      id: number;
      number: number;
      supplier: string;
      sent_at: string | null;
      expected_at: string | null;
      days_out: number | null;
    }[];
  } | null;
  lists: OrderCardList[];
  history: OrderHistoryEntry[];
};

export type OrderFormOptions = {
  branches: { id: number; name: string }[];
  /** Nie każda lokalizacja wydaje towar — hala produkcyjna nie musi. */
  pickup_points: { id: number; name: string }[];
  invoice_types: { id: number; name: string; vat_rate: number }[];
  defaults: {
    branch_id: number | null;
    invoice_type_id: number | null;
    delivery_method: string;
  };
  status: string | null;
};

export type OrderPaneRow = {
  id: number;
  position: number;
  section: string;
  product_id: number | null;
  group: string | null;
  name: string;
  thickness_mm: number | null;
  quantity: string;
  /** Cena m2 materialu. `null` znaczy brak pozycji w cenniku, nie zero. */
  unit_net_price: string | null;
  amount: string;
  /** Materiał razem z procesami — to widzi klient. */
  total: string;
  processes: {
    process_id: number;
    code: string | null;
    name: string | null;
    /** Wybrana pozycja cennikowa — null, dopóki nikt nie wybrał. */
    product_id: number | null;
    /** Nazwa wybranej pozycji: „Faza 15mm". */
    parameter: string | null;
    days: number | null;
    comment: string | null;
    unit_net_price: string;
    amount: string;
  }[];
  /** Suma dni etapów tej formatki. */
  days: number;
  price_path: {
    code: string;
    label: string;
    value: string;
    detail: string | null;
  }[];
  width_mm: number | null;
  height_mm: number | null;
  is_irregular_shape: boolean;
  is_tempered: boolean;
  needs_mark: boolean;
  is_urgent: boolean;
  /** Uwaga handlowa — może trafić na ofertę. */
  note: string | null;
  /** Instrukcja technologiczna — idzie na kartę operatora. */
  production_note: string | null;
  /** `null` = z parametrów wyceny, nie zero. */
  min_billable_m2: number | null;
  m2: number | null;
  mb: number | null;
  kg: number | null;
};

/** Pozycja cennikowa procesu — „Faza 15mm" przy grubości 8. */
export type OrderProcessItem = {
  product_id: number;
  name: string;
  /** Zawęża listę, ale jej nie rozstrzyga. `null` = niezależna od grubości. */
  glass_thickness_mm: number | null;
  unit: string;
};

export type OrderProcess = {
  id: number;
  code: string;
  name: string;
  is_subcontracted: boolean;
  /** Czas trwania ze słownika — punkt wyjścia, nie wyrok. */
  duration_days: number | null;
  items: OrderProcessItem[];
};

export type OrderItemsList = {
  id: number;
  number: number;
  name: string | null;
  role: 'component' | 'alternative';
  is_included: boolean;
  is_on_hold: boolean;
  /** `null` = jak w typie faktury. Nie to samo co `0`. */
  vat_rate: number | null;
  comment: string | null;
  net: string;
  /** Najdłuższa formatka listy — formatki idą przez halę równolegle. */
  days: number | null;
  glass: OrderPaneRow[];
  /** Okucia — własne kolumny, bo nie mają wymiarów ani procesów. */
  fittings: OrderFittingRow[];
  services: OrderPaneRow[];
};

export type OrderFittingRow = OrderPaneRow & {
  /**
   * Stan magazynowy okucia. Nazwa mówi, czym jest — w starym systemie
   * ta kolumna nazywała się „Obecna wartość" i raz pokazywała kwotę
   * pozycji, raz stan, a w modalu edycji zero.
   */
  in_stock: number | null;
  finish?: string | null;
  code?: string | null;
};

/**
 * Podział stawki obniżonej dla inwestycji mieszkaniowej.
 *
 * `share === null` znaczy **nie wiemy** — nie „zero" i nie „całość".
 * `reason` mówi, czego brakuje, żeby ekran nie musiał zgadywać.
 */
export type InvestmentVat = {
  type: 'house' | 'flat';
  type_label: string;
  reduced_rate: number;
  standard_rate: number;
  limit_m2: number | null;
  area_m2: number | null;
  share: number | null;
  is_split: boolean;
  reason: string | null;
};

export type OrderVatLine = {
  rate: number;
  net: string;
  vat: string;
  gross: string;
};

export type OrderTotals = {
  /** Suma pozycji przed rabatem. */
  base: string;
  /** Rabat łącznie, kwotowo — procenty per sekcja się nie sumują. */
  discount: string;
  net: string;
  excluded_net: string;
  /** Jedyna stawka zlecenia. `null` przy kilku stawkach albo gdy którejś nie znamy. */
  vat_rate: number | null;
  vat: string | null;
  gross: string | null;
  /** Netto bez znanej stawki — powód, dla którego brutto jest `null`. */
  unknown_net: string;
  unknown_reason: string | null;
  mixed_vat: boolean;
  vat_lines: OrderVatLine[];
  sections: {
    section: string;
    base: string;
    percent: string;
    discount: string;
    net: string;
  }[];
};

export type OrderDiscountRow = {
  section: string;
  percent: string;
  /** Ile wolno tej roli w sekcji cenowej kontrahenta. */
  max_percent: string;
  price_section: string | null;
};

export type OrderItemsBoard = {
  order: {
    id: number;
    number: number;
    status: string | null;
    contractor: string | null;
  };
  tabs: OrderTabCounts;
  lists: OrderItemsList[];
  totals: OrderTotals & {
    m2: number;
    mb: number;
    kg: number;
    days: number | null;
  };
  discounts: OrderDiscountRow[];
  vat: {
    /** Stawka z typu faktury — to znaczy `vat_rate: null` na liście. */
    default_rate: number | null;
    rates: number[];
    investment: InvestmentVat | null;
  };
  catalogue: {
    products: {
      id: number;
      name: string;
      group: string | null;
      thickness_mm: number | null;
      is_tempered_by_default: boolean;
    }[];
    processes: OrderProcess[];
    services: { id: number; name: string }[];
    fittings: {
      id: number;
      name: string;
      code: string | null;
      finish: string | null;
      dimension: string | null;
    }[];
    /** Biblioteka szablonów — bez zapisanych konfiguracji klientów. */
    sets: { id: number; name: string; items: number }[];
  };
};

export type OrderDrawingRow = {
  id: number;
  name: string;
  note: string | null;
  mime: string;
  is_image: boolean;
  size_bytes: number;
  item_id: number | null;
  item_name: string | null;
  uploaded_at: string | null;
  uploaded_by: string | null;
};

export type OrderDrawingsBoard = {
  order: { id: number; number: number; status: string | null };
  tabs: OrderTabCounts;
  drawings: OrderDrawingRow[];
  /** Komplet deklaruje człowiek — tu jest kto i kiedy. */
  complete: { declared: boolean; at: string | null; by: string | null };
  items: { id: number; name: string; list: number }[];
  accepts: string[];
  max_kilobytes: number;
};

export type OrderPaymentSummary = {
  paid: string;
  /** Bez typu faktury nie znamy brutto, więc i salda. */
  due: string | null;
  percent: number | null;
  count: number;
  currency: string;
};

export type OrderPaymentRow = {
  id: number;
  amount: string;
  currency: string;
  exchange_rate: string;
  amount_base: string;
  paid_on: string;
  register: string | null;
  note: string | null;
  /** Korekta: kwota ujemna wskazująca odwracaną wpłatę. */
  is_reversal: boolean;
  reverses_id: number | null;
  is_reversed: boolean;
  by: string | null;
};

export type OrderPaymentsBoard = {
  order: { id: number; number: number; contractor: string | null };
  tabs: OrderTabCounts;
  payments: OrderPaymentRow[];
  summary: {
    net: string;
    vat_rate: number | null;
    gross: string | null;
    paid: string;
    due: string | null;
    paid_percent: number | null;
  };
  credit: {
    limit: string;
    outstanding: string;
    payment_days: number;
  } | null;
  registers: {
    id: number;
    name: string;
    currency: string;
    /** Kasa w innej walucie niż rozliczeniowa wymaga kursu. */
    needs_rate: boolean;
  }[];
  base_currency: string;
};

/** Wycena formatki policzona bez zapisu — podgląd w panelu. */
export type PanePreview = {
  /** `false`, gdy formularz jeszcze nie ma z czego liczyć. */
  ready: boolean;
  glass_net: string | null;
  net_price_per_square_meter?: string | null;
  total: string | null;
  m2?: number;
  mb?: number;
  kg?: number;
  processes: {
    process_id: number;
    label: string;
    parameter: string | null;
    unit_net_price: string;
    /** mb, m² albo szt. — jednostka tego procesu, nie formatki. */
    unit_label: string;
    units: number;
    amount: string;
    unavailable: string | null;
  }[];
  steps: {
    code: string;
    label: string;
    value: string;
    detail: string | null;
  }[];
  unavailable?: string | null;
};

export class OrdersApi extends ApiRequest {
  static prefix: string = '/orders';

  public static async board(
    query: string = '',
    status: string | null = null,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get('', { q: query, status: status ?? '' });
  }

  public static async formOptions(): Promise<ResponseProps<ResponseContent>> {
    return await this.get('/form');
  }

  public static async create(
    data: Record<string, unknown>,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post('', data);
  }

  public static async card(
    id: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get(`/${id}`);
  }

  /**
   * Warunki przejścia są sprawdzane ponownie po stronie serwera, więc
   * ta metoda może się nie powieść mimo widocznego przycisku.
   */
  public static async items(
    id: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get(`/${id}/items`);
  }

  /** Liczy, nic nie zapisuje. */
  public static async previewPane(
    id: number,
    data: Record<string, unknown>,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/${id}/panes/preview`, data);
  }

  /** Okucie na zleceniu. Cenę liczy cennik; pole ceny jest nadpisaniem. */
  public static async saveFitting(
    id: number,
    data: Record<string, unknown>,
    itemId?: number | null,
  ): Promise<ResponseProps<ResponseContent>> {
    return itemId
      ? await this.put(`/${id}/fittings/${itemId}`, data)
      : await this.post(`/${id}/fittings`, data);
  }

  /** Rozwinięcie zestawu na pozycje listy. */
  public static async addFittingSet(
    id: number,
    data: Record<string, unknown>,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/${id}/fittings/set`, data);
  }

  public static async savePane(
    id: number,
    data: Record<string, unknown>,
    itemId?: number | null,
  ): Promise<ResponseProps<ResponseContent>> {
    return itemId
      ? await this.put(`/${id}/panes/${itemId}`, data)
      : await this.post(`/${id}/panes`, data);
  }

  public static async saveService(
    id: number,
    data: Record<string, unknown>,
    itemId?: number | null,
  ): Promise<ResponseProps<ResponseContent>> {
    return itemId
      ? await this.put(`/${id}/services/${itemId}`, data)
      : await this.post(`/${id}/services`, data);
  }

  public static async deleteItem(
    id: number,
    itemId: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.delete(`/${id}/items/${itemId}`);
  }

  public static async saveList(
    id: number,
    data: Record<string, unknown>,
    listId?: number | null,
  ): Promise<ResponseProps<ResponseContent>> {
    return listId
      ? await this.put(`/${id}/lists/${listId}`, data)
      : await this.post(`/${id}/lists`, data);
  }

  public static async deleteList(
    id: number,
    listId: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.delete(`/${id}/lists/${listId}`);
  }

  /** Przeniesienie pozycji — bez przeliczania ceny. */
  public static async moveItem(
    id: number,
    itemId: number,
    listId: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.put(`/${id}/items/${itemId}/list`, {
      order_list_id: listId,
    });
  }

  /** Rodzaj obiektu i powierzchnia użytkowa — od nich zależy stawka VAT. */
  public static async saveInvestment(
    id: number,
    data: {
      investment_type: string | null;
      investment_area_m2: string | number | null;
    },
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.put(`/${id}/investment`, data);
  }

  public static async saveDiscounts(
    id: number,
    discounts: Record<string, string>,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.put(`/${id}/discounts`, { discounts });
  }

  public static async drawings(
    id: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get(`/${id}/drawings`);
  }

  public static async addDrawing(
    id: number,
    file: File,
    itemId: number | null,
    note: string,
  ): Promise<ResponseProps<ResponseContent>> {
    const payload = new FormData();

    payload.append('file', file);
    payload.append('note', note);

    if (itemId !== null) {
      payload.append('order_item_id', String(itemId));
    }

    return await this.post(`/${id}/drawings`, payload);
  }

  public static async deleteDrawing(
    id: number,
    drawingId: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.delete(`/${id}/drawings/${drawingId}`);
  }

  public static async declareDrawings(
    id: number,
    complete: boolean,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.put(`/${id}/drawings-complete`, { complete });
  }

  /** Plik idzie przez aplikację, nie z katalogu publicznego. */
  public static drawingUrl(id: number, drawingId: number): string {
    return `${this.baseUrl ?? ''}${this.prefix}/${id}/drawings/${drawingId}`;
  }

  public static async payments(
    id: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get(`/${id}/payments`);
  }

  public static async addPayment(
    id: number,
    data: Record<string, unknown>,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/${id}/payments`, data);
  }

  /** Korekta dopisuje wiersz odwrotny — nic nie znika, stąd POST. */
  public static async reversePayment(
    id: number,
    paymentId: number,
    note: string,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/${id}/payments/${paymentId}/reverse`, { note });
  }

  public static async transition(
    id: number,
    transitionId: number,
    reason?: string,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/${id}/transition`, {
      transition_id: transitionId,
      reason: reason ?? '',
    });
  }
}
