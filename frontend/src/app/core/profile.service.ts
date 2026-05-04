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

  updateProfile(formData: FormData) {
    // 🔥 Igual que playlists: multipart + Laravel -> usar POST + _method
    formData.append('_method', 'PUT');

    return this.http.post(`${API_BASE_URL}/me/profile`, formData);
  }
}
