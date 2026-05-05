import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';

import { API_BASE_URL } from './api';
import { ProfileData, User } from './models';

export interface ProfileUpdateResponse {
  message: string;
  user: User;
}

@Injectable({ providedIn: 'root' })
export class ProfileService {
  private readonly http = inject(HttpClient);

  profile() {
    return this.http.get<ProfileData>(`${API_BASE_URL}/me/profile`);
  }

  updateProfile(formData: FormData) {
    if (!formData.has('_method')) {
      formData.append('_method', 'PUT');
    }

    return this.http.post<ProfileUpdateResponse>(`${API_BASE_URL}/me/profile`, formData);
  }
}
