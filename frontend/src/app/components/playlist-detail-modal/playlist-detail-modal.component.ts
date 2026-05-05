import { Component, input, output } from '@angular/core';
import { LucideAngularModule, Music2, Pencil, Trash2, X } from 'lucide-angular';

import { PlaylistDetail, SpotifyTrack } from '../../interfaces/music.interfaces';
import { SongRowComponent } from '../song-row/song-row.component';

@Component({
  selector: 'app-playlist-detail-modal',
  standalone: true,
  imports: [LucideAngularModule, SongRowComponent],
  styleUrl: './playlist-detail-modal.component.scss',
  templateUrl: './playlist-detail-modal.component.html',
})
export class PlaylistDetailModalComponent {
  readonly detail = input.required<PlaylistDetail>();
  readonly closed = output<void>();
  readonly edit = output<PlaylistDetail['playlist']>();
  readonly removeTrack = output<SpotifyTrack>();

  readonly icons = { Music2, Pencil, Trash2, X };
}
