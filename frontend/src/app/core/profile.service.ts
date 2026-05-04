import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';

import { API_BASE_URL } from './api';
import { ProfileData } from './models';

@Injectable({ providedIn: 'root' })
export class ProfileService {
  private readonly http = inject(HttpClient);

  profile() {
    return this.http.get<ProfileData>(`${API_BASE_URL}/me/profile`);
  }
}
