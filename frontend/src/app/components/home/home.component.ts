import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute } from '@angular/router';

import { CatalogService } from '../../services/catalog.service';
import { HomeData } from '../../interfaces/music.interfaces';
import { MediaCardComponent } from '../media-card/media-card.component';
import { SongRowComponent } from '../song-row/song-row.component';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [MediaCardComponent, SongRowComponent],
  templateUrl: './home.component.html',
  styleUrl: './home.component.scss',
})
export class HomeComponent {
  private readonly catalog = inject(CatalogService);
  private readonly route = inject(ActivatedRoute);
  private readonly destroyRef = inject(DestroyRef);

  readonly data = signal<HomeData>({
    source: 'spotify',
    artists: [],
    albums: [],
    tracks: [],
  });

  readonly loading = signal(true);

  readonly title = signal('Descubre nueva musica');
  readonly subtitle = signal(
    'Explora artistas, albumes y previews inspirados en Spotify.'
  );

  constructor() {
    this.route.queryParamMap
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((params) => {
        const query = params.get('q')?.trim() ?? '';
        this.loading.set(true);

        if (query) {
          this.title.set(`Resultados para "${query}"`);
          this.subtitle.set('Busqueda en vivo usando Spotify.');
          this.catalog.search(query).subscribe((results) => {
            this.data.set(results);
            this.loading.set(false);
          });
          return;
        }

        this.title.set('Descubre nueva musica');
        this.subtitle.set(
          'Explora artistas, albumes y previews inspirados en Spotify.'
        );

        this.catalog.home().subscribe((home) => {
          this.data.set(home);
          this.loading.set(false);
        });
      });
  }
}
