import { HttpInterceptorFn } from '@angular/common/http';

export const API_BASE_URL = 'http://localhost:8000/api';

export const authInterceptor: HttpInterceptorFn = (request, next) => {
  // Para que Laravel responda siempre JSON (y no intente redirigir en errores 401/422)
  // Angular por defecto manda Accept: */*.
  const wantsJson = request.clone({
    setHeaders: {
      Accept: 'application/json',
    },
  });

  const token = globalThis.localStorage?.getItem('musichub_token');

  if (!token || !request.url.startsWith(API_BASE_URL)) {
    return next(wantsJson);
  }

  return next(
    wantsJson.clone({
      setHeaders: {
        Authorization: `Bearer ${token}`,
      },
    }),
  );
};
