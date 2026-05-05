import { Component, computed, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Disc3, LucideAngularModule, Music2, UserRound } from 'lucide-angular';

@Component({
  selector: 'app-media-card',
  imports: [RouterLink, LucideAngularModule],
  templateUrl: './media-card.component.html',
  styleUrl: './media-card.component.scss',
})
export class MediaCardComponent {
  readonly title = input.required<string>();
  readonly subtitle = input('');
  readonly imageUrl = input<string | null>(null);
  readonly route = input.required<unknown[]>();
  readonly kind = input<'artist' | 'album' | 'playlist'>('album');
  readonly round = input(false);

  readonly icons = { Disc3, Music2, UserRound };
  readonly cacheBuster = Date.now();

  readonly resolvedImageUrl = computed(() => {
    const url = this.imageUrl();
    if (!url) return null;

    // evitar duplicar ?v si ya existe
    if (url.includes('?')) return url;

    return url + '?v=' + this.cacheBuster;
  });

  readonly fallbackIcon = computed(() => {
    if (this.kind() === 'artist') {
      return this.icons.UserRound;
    }

    return this.kind() === 'playlist' ? this.icons.Music2 : this.icons.Disc3;
  });
}



