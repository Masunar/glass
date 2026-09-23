import { ApiRequest } from './ApiRequest';

import type { ResponseContent, ResponseProps } from '@salvon/request';

export type OfferStatus = 'issued' | 'sent' | 'accepted' | 'rejected';

export type OfferRow = {
  id: number;
  /** Numer widoczny dla klienta: `24046/1`. Człon po ukośniku jest wersją. */
  number: string;
  sequence: number;
  status: OfferStatus;
  status_label: string;
  detail_level: 'summary' | 'detailed';
  sum_mode: 'components' | 'all' | 'none';
  price_display: 'net' | 'gross';
  is_variant: boolean;
  net: string;
  vat: string | null;
  /** `null`, gdy w chwili wystawienia nie znaliśmy którejś stawki VAT. */
  gross: string | null;
  valid_until: string | null;
  /**
   * Wygasła to nie to samo co odrzucona: klient nie odpowiedział,
   * a termin minął. Jedno mówi o kliencie, drugie o nas.
   */
  is_expired: boolean;
  comment: string | null;
  rejection_reason: string | null;
  accepted_list: number | null;
  issued_at: string;
  issued_by: string | null;
  sent_at: string | null;
  /** Tylko na liście wszystkich ofert. */
  contractor?: string | null;
  order_id?: number;
};

export type OfferVariant = {
  id: number;
  number: number;
  name: string | null;
  is_included: boolean;
  net: string;
};

export type OrderOffersBoard = {
  order: {
    id: number;
    number: number;
    status: string | null;
    contractor: string | null;
  };
  tabs: {
    panes: number;
    drawings: number;
    payments: number;
    offers: number;
    log: number;
  };
  offers: OfferRow[];
  /** Stan zlecenia dziś — do porównania z tym, co poszło do klienta. */
  current: {
    net: string;
    gross: string | null;
    unknown_net: string;
    unknown_reason: string | null;
    mixed_vat: boolean;
  };
  variants: OfferVariant[];
};

export type OfferMailPreview = {
  /** `null`, gdy kontrahent nie ma adresu — wtedy trzeba go wpisać. */
  to: string | null;
  subject: string;
  body: string;
  attachment: string;
  /** Adres, na który klient odpisze. */
  reply_to: string | null;
};

export type OffersBoard = {
  offers: OfferRow[];
  counts: Record<string, number>;
};

export class OffersApi extends ApiRequest {
  static prefix: string = '';

  /** Wszystkie oferty — ekran „Oferty" w menu modułu. */
  public static async board(
    status: string = '',
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get('/offers', { status });
  }

  public static async forOrder(
    orderId: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get(`/orders/${orderId}/offers`);
  }

  public static async issue(
    orderId: number,
    data: Record<string, unknown>,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/orders/${orderId}/offers`, data);
  }

  /**
   * Podgląd oferty przed wystawieniem.
   *
   * POST, bo niesie cały formularz. Odpowiedź jest plikiem **albo**
   * błędem walidacji, a przy `responseType: 'blob'` axios nie rozpakuje
   * JSON-a sam — więc rozpakowujemy go tutaj, raz, zamiast w każdym
   * miejscu wywołania.
   *
   * Zwracamy oba: `file` przy powodzeniu, `content` w kształcie reszty
   * API, żeby `validationCompleted` zadziałało tak samo jak wszędzie.
   */
  public static async preview(
    orderId: number,
    data: Record<string, unknown>,
  ): Promise<{ file: Blob | null; content: ResponseContent | null }> {
    const { content } = await this.post(
      `/orders/${orderId}/offers/preview`,
      data,
      { responseType: 'blob' },
    );

    const blob: unknown = content;

    if (blob instanceof Blob && blob.type.includes('pdf')) {
      return { file: blob, content: null };
    }

    if (!(blob instanceof Blob)) {
      return { file: null, content: (content ?? null) as ResponseContent };
    }

    // Blad przyszedl tym samym kanalem co plik. Bez tego odczytu ekran
    // pokazalby „nie udalo sie" zamiast powodu, ktory serwer podal.
    try {
      return {
        file: null,
        content: JSON.parse(await blob.text()) as ResponseContent,
      };
    } catch {
      return { file: null, content: null };
    }
  }

  /**
   * Adres pobrania PDF-u — zwykły link, nie zapytanie.
   *
   * Plik idzie przez to samo uwierzytelnienie co reszta API, więc
   * przeglądarka pobiera go sama; przepuszczanie go przez axios
   * i sklejanie bloba nie dałoby nic poza kodem do utrzymania.
   */
  public static pdfUrl(orderId: number, offerId: number): string {
    return `${this.baseUrl ?? ''}/orders/${orderId}/offers/${offerId}/pdf`;
  }

  /** Gotowa treść wiadomości do pokazania przed wysłaniem. */
  public static async mailPreview(
    orderId: number,
    offerId: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.get(`/orders/${orderId}/offers/${offerId}/mail`);
  }

  public static async send(
    orderId: number,
    offerId: number,
    data: { to: string; subject: string; body: string },
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/orders/${orderId}/offers/${offerId}/send`, data);
  }

  public static async markSent(
    orderId: number,
    offerId: number,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/orders/${orderId}/offers/${offerId}/sent`, {});
  }

  public static async accept(
    orderId: number,
    offerId: number,
    listId: number | null,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/orders/${orderId}/offers/${offerId}/accept`, {
      accepted_list_id: listId,
    });
  }

  public static async reject(
    orderId: number,
    offerId: number,
    reason: string,
  ): Promise<ResponseProps<ResponseContent>> {
    return await this.post(`/orders/${orderId}/offers/${offerId}/reject`, {
      rejection_reason: reason,
    });
  }
}
