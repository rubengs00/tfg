import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';
import { LucideAngularModule, Music2, Pencil, Trash2, X } from 'lucide-angular';

import { PlaylistDetail, SpotifyTrack } from '../../core/models';
import { SongRowComponent } from '../song-row/song-row.component';

@Component({
  selector: 'app-playlist-detail-modal',
  standalone: true,
  imports: [CommonModule, LucideAngularModule, SongRowComponent],
  styleUrl: './playlist-detail-modal.component.scss',
  templateUrl: './playlist-detail-modal.component.html',
})
export class PlaylistDetailModalComponent {
  @Input({ required: true }) detail!: PlaylistDetail;

  @Output() closed = new EventEmitter<void>();
  @Output() edit = new EventEmitter<PlaylistDetail['playlist']>();
  @Output() removeTrack = new EventEmitter<SpotifyTrack>();

  readonly icons = { Music2, Pencil, Trash2, X };
}



